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

<div style="background-color: #f8fafc; border: 1px solid #edf2f7; border-radius: 12px; margin: 24px 0; padding: 14px 16px;">
    <table role="presentation" aria-hidden="true" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            @foreach($steps as $idx => $st)
                @php
                    $isCompleted = $idx < $stepNum || ($stepNum === 4);
                    $isCurrent = $idx === $stepNum && $stepNum !== 4;
                    
                    $dotColor = $isCompleted ? '#10b981' : ($isCurrent ? '#196ecd' : '#cbd5e1');
                    $textColor = $isCompleted ? '#065f46' : ($isCurrent ? '#1e40af' : '#94a3b8');
                    $textWeight = ($isCompleted || $isCurrent) ? '700' : '500';
                    $bgPill = $isCurrent ? '#eff6ff' : ($isCompleted ? '#ecfdf5' : 'transparent');
                @endphp
                <td style="width: 25%; text-align: center; vertical-align: middle; padding: 4px 2px;">
                    <div style="display: inline-block; background-color: {{ $bgPill }}; border-radius: 9999px; padding: 4px 8px; font-size: 11.5px; font-weight: {{ $textWeight }}; color: {{ $textColor }}; letter-spacing: -0.1px;">
                        <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background-color: {{ $dotColor }}; margin-right: 5px; vertical-align: middle;"></span>
                        {{ $st['label'] }}
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
</div>
