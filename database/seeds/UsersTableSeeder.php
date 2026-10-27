<?php

use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder {
  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run() {
    // create 10 users using the user factory
    UserFactory::new()->count(10)->create();
  }
}
