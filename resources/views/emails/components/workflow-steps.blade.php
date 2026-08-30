@php
    $targetStatus = strtolower($status ?? 'open');
    $sourceStatus = strtolower($fromStatus ?? '');
    $isRevert = in_array($sourceStatus, ['resolved', 'closed']) && in_array($targetStatus, ['in_progress', 'open']);

    $stepNum = match($targetStatus) {
        'closed' => 4,
        'resolved' => 3,
        'in_progress' => 2,
        default => 1,
    };

    $steps = [
        1 => ['label' => 'Diterbitkan'],
        2 => ['label' => $isRevert ? 'Revisi' : 'Tindak Lanjut'],
        3 => ['label' => 'Verifikasi'],
        4 => ['label' => 'Selesai'],
    ];
@endphp

<table role="presentation" aria-hidden="true" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin: 20px 0; padding: 12px 14px;">
    <tr>
        @foreach($steps as $idx => $st)
            @php
                $isCompleted = $idx < $stepNum || ($stepNum === 4);
                $isCurrent = $idx === $stepNum && $stepNum !== 4;
                
                $dotColor = $isCompleted ? '#0f172a' : ($isCurrent ? '#0284c7' : '#cbd5e1');
                $textColor = $isCompleted ? '#0f172a' : ($isCurrent ? '#0284c7' : '#94a3b8');
                $textWeight = ($isCompleted || $isCurrent) ? '600' : '400';
            @endphp
            <td style="width: 25%; text-align: center; vertical-align: middle; padding: 2px 4px;">
                <div style="font-size: 11px; font-weight: {{ $textWeight }}; color: {{ $textColor }}; letter-spacing: -0.1px;">
                    <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background-color: {{ $dotColor }}; margin-right: 4px; vertical-align: middle;"></span>
                    {{ $st['label'] }}
                </div>
            </td>
        @endforeach
    </tr>
</table>
