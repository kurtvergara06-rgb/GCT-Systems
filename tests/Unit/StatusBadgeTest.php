<?php

use App\Support\StatusBadge;

it('keeps common status meanings visually distinct', function (): void {
    expect(StatusBadge::resolve('Pending'))
        ->toMatchArray(['tone' => 'pending', 'icon' => 'fa-clock'])
        ->and(StatusBadge::resolve('In Progress'))
        ->toMatchArray(['tone' => 'progress', 'icon' => 'fa-gears'])
        ->and(StatusBadge::resolve('Approved'))
        ->toMatchArray(['tone' => 'positive', 'icon' => 'fa-thumbs-up'])
        ->and(StatusBadge::resolve('Rejected'))
        ->toMatchArray(['tone' => 'negative', 'icon' => 'fa-circle-xmark'])
        ->and(StatusBadge::resolve('Under Maintenance'))
        ->toMatchArray(['tone' => 'maintenance', 'icon' => 'fa-wrench'])
        ->and(StatusBadge::resolve('Reported'))
        ->toMatchArray(['tone' => 'progress', 'icon' => 'fa-flag'])
        ->and(StatusBadge::resolve('Missed'))
        ->toMatchArray(['tone' => 'negative', 'icon' => 'fa-calendar-xmark'])
        ->and(StatusBadge::resolve('Completed'))
        ->toMatchArray(['tone' => 'success', 'icon' => 'fa-check-double']);
});

it('normalizes workflow labels without changing their displayed value', function (): void {
    expect(StatusBadge::resolve('Waiting for Delivery'))
        ->toMatchArray([
            'key' => 'waiting-for-delivery',
            'label' => 'Waiting for Delivery',
            'tone' => 'pending',
            'icon' => 'fa-truck',
        ])
        ->and(StatusBadge::resolve('Submitted', 'purchase')['label'])
        ->toBe('Under Review')
        ->and(StatusBadge::resolve(null))
        ->toMatchArray([
            'key' => 'unknown',
            'label' => 'Unknown',
            'tone' => 'neutral',
            'icon' => 'fa-circle-question',
        ]);
});

it('uses status-specific negative icons instead of one decorative icon', function (): void {
    expect(StatusBadge::resolve('Inactive')['icon'])->toBe('fa-power-off')
        ->and(StatusBadge::resolve('Cancelled')['icon'])->toBe('fa-ban')
        ->and(StatusBadge::resolve('Absent')['icon'])->toBe('fa-user-xmark')
        ->and(StatusBadge::resolve('Failed')['icon'])->toBe('fa-triangle-exclamation');
});
