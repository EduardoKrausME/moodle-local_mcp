<?php
namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

final class authenticated_identity {
    public function __construct(
        public readonly int $userid,
        public readonly string $type,
        public readonly array $scopes,
        public readonly ?int $clientid = null,
        public readonly ?int $connectionid = null,
        public readonly ?int $tokenid = null,
        public readonly ?string $family = null,
    ) {}

    public function has_scope(string $scope): bool {
        return in_array($scope, $this->scopes, true);
    }
}
