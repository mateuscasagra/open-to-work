<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Drivers\WeWorkRemotelyDriver;
use App\Enums\Modality;
use App\Enums\Seniority;
use Illuminate\Support\Facades\Http;

it('fetches and transforms WeWorkRemotely RSS into JobDTOs', function (): void {
    $rss = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0">
      <channel>
        <title>We Work Remotely</title>
        <item>
          <title>Acme Corp: Senior Ruby on Rails Engineer</title>
          <link>https://weworkremotely.com/remote-jobs/acme-corp-senior-ruby-on-rails-engineer</link>
          <guid>https://weworkremotely.com/remote-jobs/acme-corp-senior-ruby-on-rails-engineer</guid>
          <description><![CDATA[<p>Build the Rails app of your dreams. Postgres experience required.</p>]]></description>
          <pubDate>Wed, 10 Apr 2026 12:00:00 +0000</pubDate>
          <region>Anywhere in the World</region>
        </item>
        <item>
          <title>Widget Inc: Junior Frontend Developer (React)</title>
          <link>https://weworkremotely.com/remote-jobs/widget-inc-junior-frontend-developer-react</link>
          <guid>wwr-2</guid>
          <description><![CDATA[<p>Ship TypeScript + React components.</p>]]></description>
          <pubDate>Tue, 09 Apr 2026 10:00:00 +0000</pubDate>
          <region>USA</region>
        </item>
      </channel>
    </rss>
    XML;

    Http::fake([
        'weworkremotely.com/*' => Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    $results = iterator_to_array((new WeWorkRemotelyDriver())->fetch());

    expect($results)->toHaveCount(2);

    [$a, $b] = $results;

    expect($a->source)->toBe('we_work_remotely')
        ->and($a->companyName)->toBe('Acme Corp')
        ->and($a->title)->toBe('Senior Ruby on Rails Engineer')
        ->and($a->modality)->toBe(Modality::Remote)
        ->and($a->seniority)->toBe(Seniority::Senior)
        ->and($a->location)->toBe('Anywhere in the World')
        ->and($a->stack)->toContain('ruby', 'rails', 'postgres');

    expect($b->companyName)->toBe('Widget Inc')
        ->and($b->title)->toBe('Junior Frontend Developer (React)')
        ->and($b->seniority)->toBe(Seniority::Junior)
        ->and($b->location)->toBe('USA')
        ->and($b->externalId)->toBe('wwr-2')
        ->and($b->stack)->toContain('react', 'typescript');
});

it('skips items without link or title', function (): void {
    $rss = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0">
      <channel>
        <item>
          <title></title>
          <link>https://weworkremotely.com/remote-jobs/empty-title</link>
        </item>
        <item>
          <title>Good Co: Backend Dev</title>
          <link>https://weworkremotely.com/remote-jobs/good-co-backend-dev</link>
          <guid>ok</guid>
          <description>ok</description>
          <pubDate>Wed, 10 Apr 2026 12:00:00 +0000</pubDate>
          <region>Anywhere</region>
        </item>
      </channel>
    </rss>
    XML;

    Http::fake([
        'weworkremotely.com/*' => Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    $results = iterator_to_array((new WeWorkRemotelyDriver())->fetch());

    expect($results)->toHaveCount(1)
        ->and($results[0]->title)->toBe('Backend Dev');
});

it('returns empty when RSS has no items', function (): void {
    $rss = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"><channel><title>Empty</title></channel></rss>
    XML;

    Http::fake([
        'weworkremotely.com/*' => Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    $results = iterator_to_array((new WeWorkRemotelyDriver())->fetch());

    expect($results)->toBeEmpty();
});

it('gracefully handles malformed XML', function (): void {
    Http::fake([
        'weworkremotely.com/*' => Http::response('<<< not xml >>>', 200),
    ]);

    $results = iterator_to_array((new WeWorkRemotelyDriver())->fetch());

    expect($results)->toBeEmpty();
});
