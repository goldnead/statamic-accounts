<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Tests\Fixtures\EloquentUser;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\User;

/**
 * The same flows with `users.repository = eloquent` and integer ids, the way
 * ChoirLive stores its users. Nothing here may need a column Laravel's users
 * table does not have.
 */
class EloquentUsersTest extends TestCase
{
    /**
     * After AddonTestCase's own setup, which pins the file repository.
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.users.repository', 'eloquent');
        $app['config']->set('auth.providers.users.model', EloquentUser::class);
        $app['config']->set('auth.providers.users.driver', 'eloquent');
        $app['config']->set('statamic.users.repositories.eloquent.model', EloquentUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password')->nullable();
            $t->boolean('super')->default(false);
            $t->json('preferences')->nullable();
            $t->timestamp('last_login')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        // Statamic's pivot tables for Eloquent users.
        Schema::create('role_user', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('role_id');
        });

        Schema::create('group_user', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('group_id');
        });
    }

    protected function eloquentUser(string $email = 'sina@example.com'): UserContract
    {
        $model = EloquentUser::create(['name' => 'Sina Sänger', 'email' => $email, 'password' => bcrypt('geheim-123')]);

        return User::find($model->id);
    }

    #[Test]
    public function verification_writes_the_standard_column(): void
    {
        $user = $this->eloquentUser();
        $this->assertIsInt($user->id());

        $this->get(Accounts::verification()->url($user))->assertRedirect('/');

        $this->assertNotNull(EloquentUser::find($user->id())->email_verified_at);
        $this->assertTrue(Accounts::verification()->isVerified(User::find($user->id())));
    }

    #[Test]
    public function the_old_address_hears_of_the_change_with_eloquent_users(): void
    {
        Mail::fake();
        $user = $this->eloquentUser();

        $request = Accounts::emailChange()->request($user, 'neu@example.com');

        // Before the link: the old address is warned that a change was asked
        // for, so an account taken over in an open session does not move
        // silently.
        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->hasTo('sina@example.com') && $m->templateKey === 'email_change_requested');

        $this->get(Accounts::emailChange()->url($request))->assertSessionHas('accounts.status.kind', 'success');

        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->hasTo('sina@example.com') && $m->templateKey === 'email_changed');
    }

    #[Test]
    public function a_changed_password_is_announced_with_eloquent_users(): void
    {
        $user = $this->eloquentUser();
        Mail::fake();

        $fresh = User::find($user->id());
        $fresh->password('ein-ganz-neues-9');
        $fresh->save();

        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->hasTo('sina@example.com') && $m->templateKey === 'password_changed');

        Mail::fake();
        $fresh = User::find($user->id());
        $fresh->set('name', 'Sina Umbenannt');
        $fresh->save();

        Mail::assertNotSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'password_changed');
    }

    #[Test]
    public function the_address_change_and_the_deletion_work_on_integer_ids(): void
    {
        Mail::fake();
        $user = $this->eloquentUser();

        $request = Accounts::emailChange()->request($user, 'neu@example.com');
        $this->assertSame((string) $user->id(), $request->user_id);

        $this->get(Accounts::emailChange()->url($request))->assertSessionHas('accounts.status.kind', 'success');
        $this->assertSame('neu@example.com', EloquentUser::find($user->id())->email);

        Accounts::deletion()->request(User::find($user->id()));
        $this->travel(15)->days();
        $this->assertSame(1, Accounts::deletion()->purgeDue());
        $this->assertNull(EloquentUser::find($user->id()));
        Mail::assertSent(AccountMail::class, fn ($m) => $m->templateKey === 'account_deleted');
    }

    #[Test]
    public function the_export_leaves_out_the_password_and_the_remember_token(): void
    {
        $user = $this->eloquentUser();

        $account = Accounts::export()->collect($user)['sections']['account'];

        $this->assertSame('sina@example.com', $account['email']);
        $this->assertArrayNotHasKey('password', $account['data']);
        $this->assertArrayNotHasKey('remember_token', $account['data']);
    }
}
