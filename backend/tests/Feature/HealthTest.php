<?php

declare(strict_types=1);

it('reports health status', function (): void {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'timestamp',
            'services' => ['database', 'redis'],
        ]);
});
