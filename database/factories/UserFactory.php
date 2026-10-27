<?php

namespace Database\Factories;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory {
  protected $model = User::class;

  public function definition(): array {
    return [
      'title' => $this->faker->jobTitle,
      'username' => $this->faker->unique()->userName,
      'firstname' => $this->faker->firstName,
      'surname' => $this->faker->lastName,
      'password' => app('hash')->make('12345'),
      'activated' => false,
      'streetname' => $this->faker->streetName,
      'streetnumber' => (string) $this->faker->numberBetween(1, 60),
      'zipcode' => $this->faker->numberBetween(1000, 9999),
      'location' => $this->faker->city,
      'birthday' => date('Y-m-d'),
      'join_date' => date('Y-m-d'),
      'activity' => 'aktiv',
      'bv_member' => 'aktiv',
    ];
  }
}
