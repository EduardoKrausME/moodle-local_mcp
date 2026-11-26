<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Persist short, explicitly staged MCP workflows without a database upgrade.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\workflow;

use context;
use context_course;
use core\lock\lock_config;
use core_text;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry;

/**
 * Persist one independently confirmed operation per workflow step.
 *
 * Only labels, tool names and bounded server-generated postconditions are persisted, never tokens.
 */
final class sequence {
    /** Maximum number of planned operations. */
    public const MAX_ITEMS = 30;

    /** Stored workflows expire after seven days. */
    public const TTL = 604800;

    /**
     * Config key for an opaque workflow ID.
     *
     * @param string $id Workflow identifier.
     * @return string
     */
    private static function key(string $id): string {
        if (!preg_match("/^[a-f0-9]{24}$/D", $id)) {
            throw new api_exception("invalid_workflow", 400);
        }
        return "workflow_" . $id;
    }

    /**
     * Load a workflow, checking client identity, context and expiration.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Connection identity.
     * @param int $courseid Course ID.
     * @return array
     */
    private static function load(string $id, authenticated_identity $identity, int $courseid): array {
        $key = self::key($id);
        $json = get_config("local_mcp", $key);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) ||
                (int)($data["userid"] ?? 0) !== $identity->userid ||
                (int)($data["clientid"] ?? 0) !== (int)($identity->clientid ?? 0) ||
                (int)($data["connectionid"] ?? 0) !== (int)($identity->connectionid ?? 0) ||
                (string)($data["type"] ?? "") !== $identity->type ||
                (int)($data["courseid"] ?? 0) !== $courseid) {
            throw new api_exception("workflow_not_found", 404);
        }
        if ((int)$data["expires"] <= time()) {
            unset_config($key, "local_mcp");
            throw new api_exception("workflow_expired", 409);
        }
        return $data;
    }

    /**
     * Save a workflow after a guarded transition.
     *
     * @param string $id Workflow ID.
     * @param array $data Workflow record.
     * @return void
     */
    private static function save(string $id, array $data): void {
        set_config(self::key($id), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "local_mcp");
    }

    /**
     * Serialize step transitions without holding the lock during Moodle writes.
     *
     * @param string $id Workflow ID.
     * @param callable $callback Transition callback.
     * @return mixed
     */
    private static function locked(string $id, callable $callback) {
        $factory = lock_config::get_lock_factory("local_mcp");
        $lock = $factory->get_lock("workflow_" . self::key($id), 5);
        if (!$lock) {
            throw new api_exception("workflow_busy", 409);
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Return a client-safe progress snapshot.
     *
     * @param string $id Workflow ID.
     * @param array $data Stored workflow.
     * @return array
     */
    private static function snapshot(string $id, array $data): array {
        $index = (int)$data["index"];
        $items = $data["items"];
        $current = $items[$index];
        $next = $data["state"] === "done" && isset($items[$index + 1])
            ? ["step" => $index + 2, "title" => $items[$index + 1]["title"],
               "tool" => $items[$index + 1]["tool"]] : null;
        return [
            "id" => $id,
            "courseid" => (int)$data["courseid"],
            "step" => $index + 1,
            "total" => count($items),
            "title" => $current["title"],
            "tool" => $current["tool"],
            "state" => $data["state"],
            "next" => $next,
            "next_action" => $next !== null
                ? ["label" => get_string("next"), "requires_explicit_user_action" => true,
                   "action" => "workflow_sequence", "courseid" => (int)$data["courseid"],
                   "workflow_id" => $id] : null,
            "completed" => $data["completed"] ?? [],
            "failure" => $data["failure"] ?? null,
            "has_reconciliation_postcondition" => isset($current["postcondition"]),
            "generation" => (int)($data["generation"] ?? 0),
            "expires" => (int)$data["expires"],
        ];
    }

    /**
     * Create a workflow, without creating any Moodle activities.
     *
     * @param authenticated_identity $identity Current connection.
     * @param int $courseid Target course.
     * @param array $items Ordered titles and names of WRITE tools.
     * @return array
     */
    public static function start(authenticated_identity $identity, int $courseid, array $items): array {
        if (count($items) < 2 || count($items) > self::MAX_ITEMS) {
            throw new api_exception("invalid_workflow_items", 400);
        }
        $available = registry::get_tools();
        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new api_exception("invalid_workflow_items", 400);
            }
            $tool = (string)($item["tool"] ?? "");
            $title = trim((string)($item["title"] ?? ""));
            if (in_array($tool, ["workflow_sequence", "workflow_reconcile"], true) || !isset($available[$tool]) ||
                    $title === "" || core_text::strlen($title) > 160) {
                throw new api_exception("invalid_workflow_items", 400);
            }
            $clean[] = ["title" => clean_param($title, PARAM_TEXT), "tool" => $tool];
        }
        $id = bin2hex(random_bytes(12));
        $data = [
            "userid" => $identity->userid,
            "clientid" => (int)($identity->clientid ?? 0),
            "connectionid" => (int)($identity->connectionid ?? 0),
            "type" => $identity->type,
            "courseid" => $courseid,
            "items" => $clean,
            "reconciliation_version" => 1,
            "index" => 0,
            "state" => "ready",
            "completed" => [],
            "expires" => time() + self::TTL,
        ];
        self::save($id, $data);
        return self::snapshot($id, $data);
    }

    /**
     * Read the current state.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Current connection.
     * @param int $courseid Course ID.
     * @return array
     */
    public static function status(string $id, authenticated_identity $identity, int $courseid): array {
        return self::snapshot($id, self::load($id, $identity, $courseid));
    }

    /**
     * Check that this WRITE belongs to the active stage and course.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Current connection.
     * @param int $step One-based step.
     * @param string $tool Tool name.
     * @param context $context Tool's actual Moodle context.
     * @return int Current generation of the stage.
     */
    public static function assert_current(string $id, authenticated_identity $identity, int $step,
                                           string $tool, context $context): int {
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext) {
            throw new api_exception("workflow_context_mismatch", 409);
        }
        $data = self::load($id, $identity, (int)$coursecontext->instanceid);
        if ($data["state"] !== "ready" || $step !== (int)$data["index"] + 1 ||
                $tool !== $data["items"][$data["index"]]["tool"]) {
            throw new api_exception("workflow_step_mismatch", 409);
        }
        return (int)($data["generation"] ?? 0);
    }

    /**
     * Claim a stage before executing its already-approved WRITE.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Connection.
     * @param int $step One-based step.
     * @param string $tool Tool name.
     * @param context $context Actual tool context.
     * @param int|null $generation Expected generation, if supplied.
     * @param string|null $argshash Hash of the normalized tool arguments.
     * @return void
     */
    public static function claim(string $id, authenticated_identity $identity, int $step,
                                  string $tool, context $context, ?int $generation = null, ?string $argshash = null): void {
        self::locked($id, function() use ($id, $identity, $step, $tool, $context, $generation, $argshash): void {
            $currentgeneration = self::assert_current($id, $identity, $step, $tool, $context);
            if ($generation !== null && $generation !== $currentgeneration) {
                throw new api_exception("workflow_step_mismatch", 409);
            }
            $coursecontext = $context->get_course_context(false);
            $data = self::load($id, $identity, (int)$coursecontext->instanceid);
            $condition = $data["items"][$data["index"]]["postcondition"] ?? null;
            if ($condition !== null && ($argshash === null || !hash_equals($condition["argshash"], $argshash))) {
                throw new api_exception("workflow_postcondition_mismatch", 409);
            }
            $data["state"] = "executing";
            self::save($id, $data);
        });
    }

    /**
     * Record a failure, without claiming that the Moodle operation was rolled back.
     */
    public static function fail(string $id, authenticated_identity $identity, int $courseid,
            ?string $reference = null): void {
        self::locked($id, function() use ($id, $identity, $courseid, $reference): void {
            $data = self::load($id, $identity, $courseid);
            if ($data["state"] === "executing") {
                $data["state"] = "failed";
                $data["failure"] = ["time" => time(), "requires_reconciliation" => true,
                    "reference" => $reference];
                self::save($id, $data);
            }
        });
    }

    /**
     * Reopen only a failed, idempotent move; new preview and token remain mandatory.
     * Other operations may have persisted data and must be reconciled manually.
     */
    public static function retry(string $id, authenticated_identity $identity, int $courseid,
            ?int $expectedstep = null, ?int $expectedgeneration = null): array {
        return self::locked($id, function() use ($id, $identity, $courseid, $expectedstep, $expectedgeneration): array {
            $data = self::load($id, $identity, $courseid);
            if (($expectedstep !== null && $expectedstep !== (int)$data["index"] + 1) ||
                    ($expectedgeneration !== null && $expectedgeneration !== (int)($data["generation"] ?? 0))) {
                throw new api_exception("workflow_step_mismatch", 409);
            }
            if ($data["state"] !== "failed" || $data["items"][$data["index"]]["tool"] !== "move_activity") {
                throw new api_exception("workflow_reconciliation_required", 409, "", [],
                    get_string("workflowreconciliationrequired", "local_mcp"));
            }
            $data["generation"] = (int)($data["generation"] ?? 0) + 1;
            $data["state"] = "ready";
            self::save($id, $data);
            return self::snapshot($id, $data);
        });
    }

    /**
     * Finalize an executed stage; failures remain blocked for manual reconciliation.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Connection.
     * @param int $courseid Course ID.
     * @param array $result Successful tool result.
     * @return array
     */
    public static function complete(string $id, authenticated_identity $identity, int $courseid,
                                     array $result): array {
        return self::locked($id, function() use ($id, $identity, $courseid, $result): array {
            $data = self::load($id, $identity, $courseid);
            if ($data["state"] !== "executing") {
                throw new api_exception("workflow_step_mismatch", 409);
            }
            $data["state"] = "done";
            unset($data["failure"]);
            $data["completed"][] = [
                "step" => (int)$data["index"] + 1,
                "tool" => $data["items"][$data["index"]]["tool"],
                "result_id" => (int)($result["id"] ?? $result["cmid"] ?? 0),
            ];
            self::save($id, $data);
            return self::snapshot($id, $data);
        });
    }

    /**
     * Advance only after one stage has succeeded.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Connection.
     * @param int $courseid Course ID.
     * @return array
     */
    public static function next(string $id, authenticated_identity $identity, int $courseid,
            ?int $expectedstep = null, ?int $expectedgeneration = null): array {
        return self::locked($id, function() use ($id, $identity, $courseid, $expectedstep, $expectedgeneration): array {
            $data = self::load($id, $identity, $courseid);
            if (($expectedstep !== null && $expectedstep !== (int)$data["index"] + 1) ||
                    ($expectedgeneration !== null && $expectedgeneration !== (int)($data["generation"] ?? 0))) {
                throw new api_exception("workflow_step_mismatch", 409);
            }
            if ($data["state"] === "done" && isset($data["items"][$data["index"] + 1])) {
                $last = end($data["completed"]);
                if (($last["completion_method"] ?? "") === "reconciled") {
                    reconciliation_service::verify($data["items"][$data["index"]]["postcondition"], $identity, $courseid);
                }
                $data["index"]++;
                $data["state"] = "ready";
                self::save($id, $data);
            }
            return self::snapshot($id, $data);
        });
    }


    /**
     * Bind a server-generated postcondition from the first official preview.
     *
     * This records intent only, not approval or execution. Legacy plans cannot
     * acquire a condition retroactively. Subsequent previews cannot retarget it.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Connection.
     * @param int $step Current step.
     * @param context $context Original activity context.
     * @param int $generation Current generation.
     * @param string $argshash Normalized original arguments hash.
     * @param array|null $condition Trusted verifier output.
     * @return void
     */
    public static function bind_postcondition(string $id, authenticated_identity $identity, int $step,
            context $context, int $generation, string $argshash, ?array $condition): void {
        self::locked($id, function() use ($id, $identity, $step, $context, $generation, $argshash, $condition): void {
            if (self::assert_current($id, $identity, $step, "set_activity_completion", $context) !== $generation) {
                throw new api_exception("workflow_step_mismatch", 409);
            }
            $courseid = (int)$context->get_course_context(false)->instanceid;
            $data = self::load($id, $identity, $courseid);
            if (($data["reconciliation_version"] ?? 0) !== 1) {
                return;
            }
            $index = $data["index"];
            $existing = $data["items"][$index]["postcondition"] ?? null;
            if ($existing !== null) {
                if (!hash_equals($existing["argshash"], $argshash) ||
                        (int)$existing["contextid"] !== (int)$context->id ||
                        ($condition !== null && $existing !== $condition)) {
                    throw new api_exception("workflow_postcondition_mismatch", 409);
                }
                return;
            }
            if ($condition !== null) {
                if (!hash_equals($condition["argshash"], $argshash) ||
                        (int)$condition["contextid"] !== (int)$context->id) {
                    throw new api_exception("workflow_postcondition_mismatch", 409);
                }
                $data["items"][$index]["postcondition"] = $condition;
                self::save($id, $data);
            }
        });
    }

    /**
     * Retrieve the immutable condition of the current eligible stage.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Connection.
     * @param int $courseid Course ID.
     * @param int $step Expected stage.
     * @param int $generation Expected generation.
     * @return array
     */
    public static function reconciliation_candidate(string $id, authenticated_identity $identity, int $courseid,
            int $step, int $generation): array {
        $data = self::load($id, $identity, $courseid);
        if ($step !== (int)$data["index"] + 1 || $generation !== (int)($data["generation"] ?? 0)) {
            throw new api_exception("workflow_step_mismatch", 409);
        }
        if (!in_array($data["state"], ["ready", "failed"], true)) {
            throw new api_exception("workflow_reconciliation_required", 409);
        }
        $item = $data["items"][$data["index"]];
        if (($data["reconciliation_version"] ?? 0) !== 1 || empty($item["postcondition"]) ||
                $item["tool"] !== "set_activity_completion") {
            throw new api_exception("workflow_postcondition_unavailable", 409);
        }
        return ["condition" => $item["postcondition"], "state" => $data["state"]];
    }

    /**
     * Record a verified existing condition; never execute the original WRITE.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Connection.
     * @param int $courseid Course ID.
     * @param int $step Expected step.
     * @param int $generation Expected generation.
     * @param string $expectedhash Hash bound to the fresh confirmation.
     * @return array
     */
    public static function reconcile(string $id, authenticated_identity $identity, int $courseid,
            int $step, int $generation, string $expectedhash): array {
        return self::locked($id, function() use ($id, $identity, $courseid, $step, $generation, $expectedhash): array {
            $preview = reconciliation_service::preview($id, $identity, $courseid, $step, $generation);
            if (!hash_equals($preview["reconciliation_sha256"], $expectedhash)) {
                throw new api_exception("workflow_postcondition_mismatch", 409);
            }
            $data = self::load($id, $identity, $courseid);
            $data["state"] = "done";
            $data["generation"] = $generation + 1;
            unset($data["failure"]);
            $data["completed"][] = [
                "step" => $step,
                "tool" => "set_activity_completion",
                "result_id" => $preview["cmid"],
                "completion_method" => "reconciled",
                "verified" => true,
                "reconciledby" => $identity->userid,
                "reconciledat" => time(),
                "generation" => $data["generation"],
                "academic_records_changed" => false,
            ];
            self::save($id, $data);
            return [
                "cmid" => $preview["cmid"],
                "workflow_step" => $step,
                "workflow_generation" => $data["generation"],
                "completion_method" => "reconciled",
                "academic_records_changed" => false,
                "workflow" => self::snapshot($id, $data),
            ];
        });
    }

    /**
     * Cancel an owned workflow without touching course content.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Connection.
     * @param int $courseid Course ID.
     * @return void
     */
    public static function cancel(string $id, authenticated_identity $identity, int $courseid): void {
        self::locked($id, function() use ($id, $identity, $courseid): void {
            $data = self::load($id, $identity, $courseid);
            if ($data["state"] === "executing") {
                throw new api_exception("workflow_reconciliation_required", 409, "", [],
                    get_string("workflowreconciliationrequired", "local_mcp"));
            }
            unset_config(self::key($id), "local_mcp");
        });
    }
}
