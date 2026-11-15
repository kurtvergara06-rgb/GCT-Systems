<?php

use App\Support\TableSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

it('applies only whitelisted table sort fields and directions', function (): void {
    $query = Mockery::mock(Builder::class);
    $request = Request::create('/records', 'GET', [
        'sort' => 'name',
        'sort_direction' => 'desc',
    ]);

    $query->shouldReceive('reorder')->once()->with('record_name', 'desc')->andReturnSelf();
    $query->shouldReceive('orderBy')->once()->with('id', 'desc')->andReturnSelf();

    expect(TableSort::apply($query, $request, [
        'name' => 'record_name',
        'created' => 'created_at',
    ], 'created', 'asc'))->toBe($query);
});

it('falls back safely when a table sort request is not supported', function (): void {
    $query = Mockery::mock(Builder::class);
    $request = Request::create('/records', 'GET', [
        'sort' => 'DROP TABLE records',
        'sort_direction' => 'sideways',
    ]);

    $query->shouldReceive('reorder')->once()->with('created_at', 'desc')->andReturnSelf();
    $query->shouldReceive('orderBy')->once()->with('id', 'desc')->andReturnSelf();

    expect(TableSort::apply($query, $request, [
        'name' => 'record_name',
        'created' => 'created_at',
    ], 'created', 'desc'))->toBe($query);
});
