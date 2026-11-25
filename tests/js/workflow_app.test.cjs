// Test the actual inline resource against a simulated MCP Apps host.
const { test } = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const path = require("node:path");
const template = fs.readFileSync(path.join(__dirname, "../../templates/workflow_next.mustache"), "utf8");
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];
const tick = () => new Promise(resolve => setImmediate(resolve));
function harness(legacy = false) {
    const elements = new Map(), listeners = new Map(), sent = [], legacySent = [];
    const element = id => {
        if (!elements.has(id)) elements.set(id, {
            id, hidden: false, textContent: id.replace("label-", ""), disabled: false,
            setAttribute() {}, getBoundingClientRect: () => ({height: 100}),
            addEventListener(name, cb) { this[name] = cb; },
        });
        return elements.get(id);
    };
    const parent = {postMessage(message) { sent.push(message); }};
    const window = {parent, addEventListener: (n, f) => listeners.set(n, f),
        removeEventListener: n => listeners.delete(n)};
    const timers = new Map();
    let timer = 0;
    const w = {id: "a".repeat(24), courseid: 219, step: 1, total: 2, generation: 0,
        state: "done", title: "<img onerror=bad>", tool: "move_activity", next: {step: 2}};
    const output = {workflow: w, moodle_wwwroot: "https://example.org"};
    if (legacy) window.openai = {toolOutput: output,
        sendFollowUpMessage: async args => {legacySent.push(args);}};
    vm.runInNewContext(script, {window, document: {getElementById: element, documentElement: element("root")},
        URL, Map, Number, Promise, setTimeout: fn => {timers.set(++timer, fn); return timer;},
        clearTimeout: id => timers.delete(id)});
    function receive(data, source = parent) {
        listeners.get("message")?.({source, origin: "https://host.example", data: {jsonrpc: "2.0", ...data}});
    }
    return {sent, legacySent, element, receive, output, timers};
}
test("standard handshake, result and user action; no direct write or duplicate", async () => {
    const h = harness();
    assert.equal(h.sent[0].method, "ui/initialize");
    h.receive({id: h.sent[0].id, result: {protocolVersion: "2026-01-26", hostCapabilities: {message: {text: {}}}}});
    await tick();
    assert.equal(h.sent[1].method, "ui/notifications/initialized");
    h.receive({method: "ui/notifications/tool-result", params: {structuredContent: h.output}}, {});
    assert.equal(h.element("progress").textContent, "progress");
    h.receive({method: "ui/notifications/tool-result", params: {structuredContent: h.output}});
    assert.equal(h.element("title").textContent, "<img onerror=bad>");
    h.element("next").click(); h.element("next").click();
    const messages = h.sent.filter(x => x.method === "ui/message");
    assert.equal(messages.length, 1);
    assert.match(messages[0].params.content[0].text, /expectedstep=1/);
    assert.match(messages[0].params.content[0].text, /Never approve/);
    assert.ok(!h.sent.some(x => x.method === "tools/call"));
    h.receive({id: messages[0].id, result: {}});
    await tick();
    assert.equal(h.element("next").disabled, true);
});
test("legacy fallback, failed non-move and cancellation", async () => {
    const h = harness(true);
    h.receive({id: h.sent[0].id, error: {code: -32601}});
    await tick();
    h.element("next").click();
    await tick();
    assert.equal(h.legacySent.length, 1);
    const x = harness();
    x.receive({id: x.sent[0].id, result: {protocolVersion: "2026-01-26", hostCapabilities: {message: {text: {}}}}});
    await tick();
    x.output.workflow.state = "failed";
    x.output.workflow.tool = "create_section";
    x.receive({method: "ui/notifications/tool-result", params: {structuredContent: x.output}});
    assert.equal(x.element("retry").hidden, true);
    assert.equal(x.element("refresh").hidden, false);
    x.receive({method: "ui/notifications/tool-cancelled", params: {}});
    assert.equal(x.element("refresh").hidden, true);
});

test("failed move error includes recovery, denied message is not resent", async () => {
    const h = harness(true);
    h.receive({id: h.sent[0].id, result: {protocolVersion: "2026-01-26", hostCapabilities: {message: {text: {}}}}});
    await tick();
    h.output.workflow.state = "failed";
    h.output.workflow.failure = {reference: "abc123"};
    h.receive({method: "ui/notifications/tool-result", params: {isError: true, structuredContent: h.output}});
    assert.equal(h.element("retry").hidden, false);
    assert.equal(h.element("reference").textContent, "abc123");
    h.element("retry").click();
    const msg = h.sent.find(x => x.method === "ui/message");
    assert.match(msg.params.content[0].text, /action=retry/);
    h.receive({id: msg.id, result: {isError: true}});
    await tick();
    h.element("retry").click();
    assert.equal(h.sent.filter(x => x.method === "ui/message").length, 1);
    assert.equal(h.legacySent.length, 0);
    assert.equal(h.element("status").textContent, "error");
});
test("teardown acknowledges host and prevents further requests", async () => {
    const h = harness();
    h.receive({id: h.sent[0].id, result: {protocolVersion: "2026-01-26", hostCapabilities: {message: {text: {}}}}});
    await tick();
    h.receive({method: "ui/notifications/tool-result", params: {structuredContent: h.output}});
    h.receive({id: 99, method: "ui/resource-teardown", params: {}});
    assert.equal(h.sent.at(-1).id, 99);
    h.element("next").click();
    assert.ok(!h.sent.some(x => x.method === "ui/message"));
});

test("host without message capability displays status but cannot send", async () => {
    const h = harness(true);
    h.receive({id: h.sent[0].id, result: {protocolVersion: "2026-01-26", hostCapabilities: {}}});
    await tick();
    h.receive({method: "ui/notifications/tool-result", params: {structuredContent: h.output}});
    assert.equal(h.element("next").hidden, true);
    assert.equal(h.element("status").textContent, "unsupported");
    h.element("next").click();
    assert.equal(h.sent.filter(x => x.method === "ui/message").length, 0);
    assert.equal(h.legacySent.length, 0);
});
test("text-only result and cancellation during a pending request", async () => {
    const h = harness();
    h.receive({id: h.sent[0].id, result: {protocolVersion: "2026-01-26", hostCapabilities: {message: {text: {}}}}});
    await tick();
    h.receive({method: "ui/notifications/tool-result", params: {content: [{type: "text", text: JSON.stringify(h.output)}]}});
    assert.equal(h.element("next").hidden, false);
    h.element("next").click();
    const msg = h.sent.find(x => x.method === "ui/message");
    h.receive({method: "ui/notifications/tool-cancelled", params: {}});
    h.receive({id: msg.id, error: {code: -32603}});
    await tick();
    assert.equal(h.element("status").textContent, "cancelled");
    assert.equal(h.element("title").textContent, "");
    assert.equal(h.element("progress").hidden, true);
});
test("reject unsupported protocol version and unrelated message origins", async () => {
    const h = harness();
    h.receive({id: h.sent[0].id, result: {protocolVersion: "unknown", hostCapabilities: {message: {text: {}}}}});
    await tick();
    h.receive({method: "ui/notifications/tool-result", params: {structuredContent: h.output}});
    assert.equal(h.sent.filter(x => x.method === "ui/notifications/initialized").length, 0);
    assert.equal(h.element("status").textContent, "unsupported");
});
