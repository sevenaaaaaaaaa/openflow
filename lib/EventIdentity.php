<?php
/** Optional stable identity shared by event ingestion and Flow dispatch. */

if (!function_exists('event_identity')) {
    function event_identity(array $source = []): string {
        foreach (['event_id', 'message_id', 'idempotency_key', '_event_id'] as $field) {
            $value = trim((string)($source[$field] ?? ''));
            if ($value !== '') return substr($value, 0, 64);
        }
        return 'evt_' . bin2hex(random_bytes(16));
    }

    /** Existing Flow trigger payload plus an optional stable event identity. */
    function flow_trigger_context(array $ctx, string $email, string $uid, string $memberId): array {
        $base = ['email'=>$email, 'uid'=>$uid, 'member_id'=>$memberId, 'label'=>$ctx['label'] ?? '', 'page'=>$ctx['page'] ?? ''];
        $eventId = trim((string)($ctx['event_id'] ?? ($ctx['idempotency_key'] ?? ($ctx['_event_id'] ?? ''))));
        if ($eventId !== '') $base['event_id'] = $eventId;
        return array_merge($base, $ctx['props'] ?? []);
    }
}
