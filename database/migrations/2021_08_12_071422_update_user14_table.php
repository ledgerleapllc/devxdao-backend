<?php

use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class UpdateUser14Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_login_ip_address')->nullable();
            $table->tinyInteger('is_super_admin')->nullable()->default(0);
        });
        $role = Role::where(['name' => 'super-admin'])->first();
        if (!$role) Role::create(['name' => 'super-admin']);
        $users = User::where('is_admin', 1)->get();
        foreach($users as $user) {
            $user->is_super_admin = 1;
            $user->save();
            $user->assignRole('super-admin');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
