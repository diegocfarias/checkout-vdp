<div style="padding: 16px;">
    <div style="margin-bottom: 12px;">
        <strong style="color: var(--gray-700, #374151);">Rota:</strong>
        <span>{{ $log->showcaseRoute?->routeLabel() ?? '—' }}</span>
    </div>
    <div style="margin-bottom: 12px;">
        <strong style="color: var(--gray-700, #374151);">Início:</strong>
        <span>{{ $log->started_at?->format('d/m/Y H:i:s') ?? '—' }}</span>
    </div>
    <div style="margin-bottom: 12px;">
        <strong style="color: var(--gray-700, #374151);">Erro:</strong>
    </div>
    <div style="background: var(--danger-50, #fef2f2); border: 1px solid var(--danger-200, #fecaca); border-radius: 8px; padding: 12px; font-family: monospace; font-size: 13px; color: var(--danger-700, #b91c1c); white-space: pre-wrap; word-break: break-all;">{{ $log->error_message }}</div>
</div>
