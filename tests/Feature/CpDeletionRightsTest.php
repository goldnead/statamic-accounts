<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\FakesRoles;

/**
 * Scheduling a deletion from the Control Panel is deleting a user, so it is
 * core's `delete users` (UserPolicy::delete) that decides, through real
 * roles rather than a gate shortcut. A super admin is only deleted by a
 * super admin.
 */
class CpDeletionRightsTest extends TestCase
{
    use FakesRoles;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->setTestRoles([
            'support' => ['access cp', 'view accounts', 'manage accounts'],
            'deleter' => ['access cp', 'view accounts', 'manage accounts', 'delete users'],
        ]);
    }

    protected function admin(string $role, bool $super = false): \Statamic\Contracts\Auth\User
    {
        $user = User::make()->email($role.($super ? '-super' : '').'@example.com')->assignRole($role);
        $user->makeSuper($super ?: null);

        if (! $super) {
            $user->set('super', false);
        }

        $user->save();

        return $user;
    }

    #[Test]
    public function manage_accounts_alone_does_not_delete(): void
    {
        $customer = $this->makeUser();

        $this->actingAs($this->admin('support'))
            ->postJson(cp_route('accounts.customers.deletion.schedule', $customer->id()))
            ->assertForbidden();

        $this->assertNull(Accounts::deletion()->pending($customer));
    }

    #[Test]
    public function delete_users_schedules_the_deletion(): void
    {
        $customer = $this->makeUser();

        $this->actingAs($this->admin('deleter'))
            ->post(cp_route('accounts.customers.deletion.schedule', $customer->id()))
            ->assertRedirect();

        $this->assertNotNull(Accounts::deletion()->pending($customer));
    }

    #[Test]
    public function a_super_admin_is_only_deleted_by_a_super_admin(): void
    {
        $super = $this->makeUser('chefin@example.com', ['super' => true]);

        $this->actingAs($this->admin('deleter'))
            ->postJson(cp_route('accounts.customers.deletion.schedule', $super->id()))
            ->assertForbidden();
        $this->assertNull(Accounts::deletion()->pending($super));

        $other = User::make()->email('root@example.com')->makeSuper();
        $other->save();

        $this->actingAs($other)
            ->post(cp_route('accounts.customers.deletion.schedule', $super->id()))
            ->assertRedirect();
        $this->assertNotNull(Accounts::deletion()->pending($super));
    }
}
