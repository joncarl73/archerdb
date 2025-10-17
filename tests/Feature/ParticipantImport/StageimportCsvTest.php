<?php

use App\Models\Company;
use App\Models\League;
use App\Models\ParticipantImport;
use App\Models\PricingTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure the component writes to the faked "local" disk.
    config(['filesystems.default' => 'local']);
    Storage::fake('local');
});

it('stages import with unit_price_cents from company pricing tier', function () {
    // Company + tier @ $12.00
    $tier = PricingTier::factory()->create([
        'name' => 'Tier A - '.uniqid(), // avoid unique collisions across runs
        'league_participant_fee_cents' => 1200,
        'currency' => 'usd',
        'is_active' => true,
    ]);

    $company = Company::factory()->create([
        'pricing_tier_id' => $tier->id,
    ]);

    $owner = User::factory()->create([
        'company_id' => $company->id,
    ]);

    // League owned by $owner, type open (CSV allowed)
    $league = League::factory()->create([
        'owner_id' => $owner->id,
        'company_id' => $company->id,
        'type' => 'open',
    ]);

    actingAs($owner);

    // One new billable row
    $csv = "first_name,last_name,email\nJane,Doe,jane@example.com\n";
    $file = UploadedFile::fake()->createWithContent('import.csv', $csv, 'text/csv');

    $lw = Volt::test('leagues.participants', ['league' => $league])
        ->set('csv', $file)
        ->call('stageImportCsv');

    // The import should now exist
    $import = ParticipantImport::query()->latest('id')->first();
    expect($import)->not->toBeNull();

    // Assert redirect to confirm for THIS import id
    $lw->assertRedirect(
        route('corporate.leagues.participants.import.confirm', [
            'league' => $league->id,
            'import' => $import->id,
        ])
    );

    expect($import->league_id)->toBe($league->id)
        ->and($import->row_count)->toBe(1)
        ->and($import->unit_price_cents)->toBe(1200)      // from company tier
        ->and($import->amount_cents)->toBe(1200)          // 1 × 1200
        ->and(strtolower($import->currency))->toBe('usd')
        ->and($import->status)->toBe('pending_payment');
});

it('computes total and unit consistently when more than one new row', function () {
    // Tier @ $8.00
    $tier = PricingTier::factory()->create([
        'name' => 'Tier B - '.uniqid(),
        'league_participant_fee_cents' => 800,
        'currency' => 'usd',
        'is_active' => true,
    ]);

    $company = Company::factory()->create([
        'pricing_tier_id' => $tier->id,
    ]);

    $owner = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $league = League::factory()->create([
        'owner_id' => $owner->id,
        'company_id' => $company->id,
        'type' => 'open',
    ]);

    actingAs($owner);

    // Two unique billable + one duplicate (dedup within CSV)
    $csv = implode("\n", [
        'first_name,last_name,email',
        'Amy,Archer,amy@example.com',
        'Bob,Bowman,bob@example.com',
        'Amy,Archer,amy@example.com', // duplicate row
        '', // trailing newline
    ]);
    $file = UploadedFile::fake()->createWithContent('import.csv', $csv, 'text/csv');

    Volt::test('leagues.participants', ['league' => $league])
        ->set('csv', $file)
        ->call('stageImportCsv'); // redirect happens; assert DB directly

    $import = ParticipantImport::query()->latest('id')->first();

    // 2 billable × $8.00 = $16.00
    expect($import)->not->toBeNull()
        ->and($import->row_count)->toBe(2)          // dedup worked
        ->and($import->unit_price_cents)->toBe(800) // from tier
        ->and($import->amount_cents)->toBe(1600)    // 2 × 800
        ->and(strtolower($import->currency))->toBe('usd')
        ->and($import->status)->toBe('pending_payment');
});
