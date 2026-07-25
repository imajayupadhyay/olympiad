<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Coupon;
use App\Models\Referral;
use App\Models\ReferralClick;
use App\Models\ReferralSetting;
use App\Models\ReferralShare;
use App\Models\User;
use App\Services\CouponService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferralSystemTest extends TestCase
{
    use RefreshDatabase;

    private function activeProgram(array $overrides = []): ReferralSetting
    {
        $settings = ReferralSetting::current();
        $settings->update(array_merge([
            'is_active'                => true,
            'referee_discount_type'    => 'percentage',
            'referee_discount_value'   => 10,
            'referee_max_discount'     => 50,
            'referee_min_order_amount' => 0,
            'referrer_reward_type'     => 'fixed',
            'referrer_reward_value'    => 100,
            'unlock_threshold'         => 1,
            'qualify_on'               => 'registration',
        ], $overrides));

        return $settings->refresh();
    }

    public function test_referral_link_attributes_a_new_signup(): void
    {
        $this->activeProgram();
        $referrer = User::factory()->create(['is_active' => true]);
        $classLevel = ClassLevel::create(['level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5]);

        // Land with the referral code, then register.
        $this->get('/register?ref='.$referrer->referral_code);
        $this->post('/register', [
            'name'                  => 'New Student',
            'email'                 => 'newbie@example.com',
            'class_level_id'        => $classLevel->id,
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $referee = User::where('email', 'newbie@example.com')->first();
        $this->assertNotNull($referee);
        $this->assertSame($referrer->id, $referee->referred_by);

        $referral = Referral::where('referee_id', $referee->id)->first();
        $this->assertNotNull($referral);
        $this->assertSame($referrer->id, $referral->referrer_id);
        $this->assertNotNull($referral->referee_welcome_coupon_id);

        // Welcome coupon is personal to the referee.
        $coupon = Coupon::find($referral->referee_welcome_coupon_id);
        $this->assertSame('referral_welcome', $coupon->source);
        $this->assertSame($referee->id, $coupon->owner_user_id);
    }

    public function test_self_referral_is_blocked(): void
    {
        $this->activeProgram();
        $user = User::factory()->create(['is_active' => true]);

        $result = app(ReferralService::class)->attribute($user, $user->referral_code);

        $this->assertNull($result);
        $this->assertSame(0, Referral::count());
    }

    public function test_inactive_program_does_not_attribute(): void
    {
        ReferralSetting::current()->update(['is_active' => false]);
        $referrer = User::factory()->create(['is_active' => true]);
        $referee = User::factory()->create(['is_active' => true]);

        $result = app(ReferralService::class)->attribute($referee, $referrer->referral_code);

        $this->assertNull($result);
        $this->assertSame(0, Referral::count());
    }

    public function test_a_user_can_only_be_referred_once(): void
    {
        $this->activeProgram();
        $svc = app(ReferralService::class);
        $referrerA = User::factory()->create(['is_active' => true]);
        $referrerB = User::factory()->create(['is_active' => true]);
        $referee = User::factory()->create(['is_active' => true]);

        $this->assertNotNull($svc->attribute($referee, $referrerA->referral_code));
        $this->assertNull($svc->attribute($referee, $referrerB->referral_code));
        $this->assertSame(1, Referral::where('referee_id', $referee->id)->count());
    }

    public function test_reward_repeats_every_threshold(): void
    {
        $this->activeProgram(['unlock_threshold' => 2]);
        $svc = app(ReferralService::class);
        $referrer = User::factory()->create(['is_active' => true]);
        $refer = fn () => $svc->attribute(User::factory()->create(['is_active' => true]), $referrer->referral_code);

        $refer();
        $this->assertSame(0, $this->rewardCount($referrer), 'No reward before threshold (1 < 2)');

        $refer();
        $this->assertSame(1, $this->rewardCount($referrer), 'Reward minted at threshold (2)');

        $refer();
        $this->assertSame(1, $this->rewardCount($referrer), 'Mid-cycle — no new reward (3)');

        $refer();
        $this->assertSame(2, $this->rewardCount($referrer), 'Repeatable — second reward at 4');
    }

    public function test_first_paid_enrollment_mode_defers_qualification(): void
    {
        $this->activeProgram(['qualify_on' => 'first_paid_enrollment', 'unlock_threshold' => 1]);
        $svc = app(ReferralService::class);
        $referrer = User::factory()->create(['is_active' => true]);
        $referee = User::factory()->create(['is_active' => true]);

        $svc->attribute($referee, $referrer->referral_code);
        $this->assertSame('pending', $referee->referralRecord->status);
        $this->assertSame(0, $this->rewardCount($referrer));

        // Wrong trigger is a no-op; correct trigger qualifies + rewards.
        $svc->qualifyReferral($referee, 'registration');
        $this->assertSame('pending', $referee->referralRecord()->first()->status);

        $svc->qualifyReferral($referee, 'first_paid_enrollment');
        $this->assertSame('rewarded', $referee->referralRecord()->first()->status);
        $this->assertSame(1, $this->rewardCount($referrer));
    }

    public function test_personal_coupon_owner_guard(): void
    {
        $this->activeProgram();
        $svc = app(ReferralService::class);
        $cs = app(CouponService::class);
        $referrer = User::factory()->create(['is_active' => true]);
        $referee = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);

        $svc->attribute($referee, $referrer->referral_code);
        $welcome = Coupon::where('owner_user_id', $referee->id)->where('source', 'referral_welcome')->first();

        $this->assertFalse($cs->validate($welcome->code, 400, $other)['ok']);
        $this->assertTrue($cs->validate($welcome->code, 400, $referee)['ok']);
    }

    public function test_referee_welcome_discount_auto_selects(): void
    {
        $this->activeProgram();
        $svc = app(ReferralService::class);
        $cs = app(CouponService::class);
        $referrer = User::factory()->create(['is_active' => true]);
        $referee = User::factory()->create(['is_active' => true]);

        $svc->attribute($referee, $referrer->referral_code);

        $auto = $svc->autoCouponFor($referee, 400);
        $this->assertNotNull($auto);
        $this->assertSame('referral_welcome', $auto->source);
        $this->assertSame(40.0, $cs->discountFor($auto, 400)); // 10% of 400, under the ₹50 cap
    }

    public function test_link_click_mode_rewards_on_threshold_of_opens(): void
    {
        $this->activeProgram(['qualify_on' => 'link_click', 'unlock_threshold' => 2]);
        $svc = app(ReferralService::class);
        $referrer = User::factory()->create(['is_active' => true]);
        $code = $referrer->referral_code;

        $svc->recordClickByCode($code, '10.0.0.1');
        $svc->recordClickByCode($code, '10.0.0.1'); // same IP — de-duped
        $this->assertSame(1, ReferralClick::where('referrer_id', $referrer->id)->count());
        $this->assertSame(0, $this->rewardCount($referrer));

        $svc->recordClickByCode($code, '10.0.0.2');
        $this->assertSame(2, ReferralClick::where('referrer_id', $referrer->id)->count());
        $this->assertSame(1, $this->rewardCount($referrer), 'Reward minted at click threshold');

        $svc->recordClickByCode($code, '10.0.0.3'); // mid-cycle (3 of next 4)
        $this->assertSame(1, $this->rewardCount($referrer));

        $svc->recordClickByCode($code, '10.0.0.4'); // 4 opens → second reward (repeatable)
        $this->assertSame(2, $this->rewardCount($referrer));
        $this->assertSame(4, $svc->progressCount($referrer));
    }

    public function test_referrer_does_not_count_opening_own_link(): void
    {
        $this->activeProgram(['qualify_on' => 'link_click', 'unlock_threshold' => 1]);
        $svc = app(ReferralService::class);
        $referrer = User::factory()->create(['is_active' => true]);

        // Referrer opens their own link — must not count.
        $svc->recordClickByCode($referrer->referral_code, '10.0.0.1', $referrer->id);
        $this->assertSame(0, ReferralClick::count());
        $this->assertSame(0, $this->rewardCount($referrer));

        // Someone else opening it counts.
        $svc->recordClickByCode($referrer->referral_code, '10.0.0.2', null);
        $this->assertSame(1, ReferralClick::count());
        $this->assertSame(1, $this->rewardCount($referrer));
    }

    public function test_link_click_mode_ignores_unknown_or_inactive(): void
    {
        $this->activeProgram(['qualify_on' => 'link_click', 'unlock_threshold' => 1]);
        $svc = app(ReferralService::class);

        $svc->recordClickByCode('NOPE1234', '10.0.0.9');           // unknown code
        $this->assertSame(0, ReferralClick::count());

        ReferralSetting::current()->update(['is_active' => false]); // program off
        $referrer = User::factory()->create(['is_active' => true]);
        $svc->recordClickByCode($referrer->referral_code, '10.0.0.9');
        $this->assertSame(0, ReferralClick::count());
    }

    public function test_link_share_mode_counts_every_share_and_rewards_on_threshold(): void
    {
        $this->activeProgram(['qualify_on' => 'link_share', 'unlock_threshold' => 2]);
        $svc = app(ReferralService::class);
        $referrer = User::factory()->create(['is_active' => true]);

        // Every share counts (no de-dupe), unlike link_click.
        $svc->recordShare($referrer, 'copy');
        $svc->recordShare($referrer, 'copy'); // same channel — still counts
        $this->assertSame(2, ReferralShare::where('referrer_id', $referrer->id)->count());
        $this->assertSame(2, $svc->progressCount($referrer));
        $this->assertSame(1, $this->rewardCount($referrer), 'Reward minted at share threshold (2)');

        $svc->recordShare($referrer, 'whatsapp'); // mid-cycle (3 of next 4)
        $this->assertSame(1, $this->rewardCount($referrer));

        $svc->recordShare($referrer, 'email'); // 4 shares → second reward (repeatable)
        $this->assertSame(2, $this->rewardCount($referrer));
        $this->assertSame(4, $svc->progressCount($referrer));
    }

    public function test_record_share_is_a_no_op_outside_link_share_mode(): void
    {
        $this->activeProgram(['qualify_on' => 'registration', 'unlock_threshold' => 1]);
        $svc = app(ReferralService::class);
        $referrer = User::factory()->create(['is_active' => true]);

        $svc->recordShare($referrer, 'copy');
        $this->assertSame(0, ReferralShare::count());
        $this->assertSame(0, $this->rewardCount($referrer));

        // Also a no-op when the program is off, even in link_share mode.
        ReferralSetting::current()->update(['qualify_on' => 'link_share', 'is_active' => false]);
        $svc->recordShare($referrer, 'copy');
        $this->assertSame(0, ReferralShare::count());
    }

    public function test_track_share_endpoint_records_a_share(): void
    {
        $this->activeProgram(['qualify_on' => 'link_share', 'unlock_threshold' => 1]);
        $referrer = User::factory()->create(['is_active' => true]);

        $this->actingAs($referrer)
            ->from(route('student.referrals'))
            ->post(route('student.referrals.track-share'), ['channel' => 'copy'])
            ->assertRedirect();

        $this->assertSame(1, ReferralShare::where('referrer_id', $referrer->id)->count());
        $this->assertSame(1, $this->rewardCount($referrer), 'Threshold 1 → reward on first share');
    }

    /**
     * Every mode the settings screen offers must actually persist.
     *
     * Regression guard: `qualify_on` was left as a MySQL enum missing 'link_share',
     * so saving that mode died with "Data truncated for column 'qualify_on'". The
     * suite runs on sqlite, which ignores enum constraints — which is precisely why
     * it went unnoticed. See 2026_07_25_110000_fix_referral_settings_qualify_on_column.
     */
    public function test_every_qualify_on_mode_can_be_saved_from_the_admin_screen(): void
    {
        $this->activeProgram();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        foreach (['registration', 'first_paid_enrollment', 'link_click', 'link_share'] as $mode) {
            $this->actingAs($admin)
                ->from(route('admin.referrals.settings'))
                ->put(route('admin.referrals.settings.update'), [
                    'is_active'                => true,
                    'referee_discount_type'    => 'percentage',
                    'referee_discount_value'   => '10.00',
                    'referee_max_discount'     => '100.00',
                    'referee_min_order_amount' => '0.00',
                    'referrer_reward_type'     => 'fixed',
                    'referrer_reward_value'    => '50.00',
                    'referrer_max_discount'    => null,
                    'unlock_threshold'         => 1,
                    'qualify_on'               => $mode,
                    'reward_validity_days'     => null,
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect();

            $this->assertSame($mode, ReferralSetting::current()->refresh()->qualify_on,
                "qualify_on should persist as '{$mode}'");
        }
    }

    /**
     * The column must be a free-form string, not an enum that has to be migrated
     * every time a mode is added. Only MySQL can express this, so skip elsewhere.
     */
    public function test_qualify_on_is_not_a_restrictive_enum(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Column type check is MySQL-specific.');
        }

        $column = DB::selectOne("SHOW COLUMNS FROM referral_settings WHERE Field = 'qualify_on'");

        $this->assertStringNotContainsStringIgnoringCase('enum', $column->Type,
            'qualify_on must be a string so new qualification modes need no migration.');
    }

    private function rewardCount(User $referrer): int
    {
        return Coupon::where('owner_user_id', $referrer->id)->where('source', 'referral_reward')->count();
    }
}
