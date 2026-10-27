<?php

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run() {
    Model::unguard();
    // Register the user seeder
    UserFactory::new()->count(50)->create();
    Model::reguard();
  }
}
