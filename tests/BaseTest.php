<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\Factories\UserFactory;

class BaseTest extends TestCase {
  use DatabaseMigrations;

  public function testExample(): void {
    $this->get('/');

    self::assertEquals('Running DatePoll-Backend! ( ͡° ͜ʖ ͡°)', $this->response->getContent());
  }

  public function testJWTMiddlewareWithoutJWT(): void {
    $data = [
      'test' => 'Test', ];
    $response = $this->post('/api/v1/management/users', $data);
    $response->assertResponseStatus(401);
    $response->seeJsonContains(['error_code' => 'token_not_provided']);
  }

  public function testJWTMiddlewareWithExpiredJWT(): void {
    $data = [
      'test' => 'Test', ];
    $response = $this->post('/api/v1/management/users?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJsdW1lbi1qd3QiLCJzdWIiOjEsImlhdCI6MTU1MjkxMzg5NiwiZXhwIjoxNTUyOTM1NDk2fQ.9bUazZR5sRf5Pyvpsxzd06r_TeUc68RSlnSC85aOibU', $data);
    $response->assertResponseStatus(401);
    $response->seeJsonStructure(['error_code']);
  }

  public function testJWTMiddlewareWithIncorrectJWT(): void {
    $data = [
      'test' => 'Test', ];
    $response = $this->post('/api/v1/management/users?token=eyJ0eXAiOiJKV1QiLCJhbGcasdffffffffffffff', $data);
    $response->assertResponseStatus(401);
    $response->seeJsonContains(['error_code' => 'token_incorrect']);
  }

  public function testPreviouslyIssuedHs256TokensStillWork(): void {
    $user = UserFactory::createUser('legacy-token-user');
    $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $header = $encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = $encode(json_encode(['iss' => 'lumen-jwt', 'sub' => $user->id, 'iat' => time(), 'exp' => time() + 3600]));
    $signature = $encode(hash_hmac('sha256', $header . '.' . $payload, env('JWT_SECRET'), true));

    $this->get('/api/v1/management/users?token=' . $header . '.' . $payload . '.' . $signature);

    $this->assertResponseStatus(200);
    $this->seeJsonContains(['username' => 'legacy-token-user']);
  }

  public function testExpiredSignedTokenPreservesErrorCode(): void {
    $token = JWT::encode(['sub' => 1, 'iat' => time() - 7200, 'exp' => time() - 3600], env('JWT_SECRET'), 'HS256');

    $this->get('/api/v1/management/users?token=' . $token);

    $this->assertResponseStatus(401);
    $this->seeJsonContains(['error_code' => 'token_expired']);
  }

  public function testSigninIssuesAWorkingHs256Token(): void {
    $user = UserFactory::createUser('signin-user');
    $user->password = app('hash')->make('test-password' . $user->id);
    $user->save();

    $this->post('/api/auth/signin', ['username' => 'signin-user', 'password' => 'test-password']);

    $this->assertResponseStatus(200);
    $token = json_decode($this->response->getContent(), true)['token'];
    $claims = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
    self::assertSame($user->id, $claims->sub);
    self::assertSame('lumen-jwt', $claims->iss);

    $this->get('/api/v1/management/users?token=' . $token);
    $this->assertResponseStatus(200);
  }

  public function testEmailChangeRejectsControlsAndNonStringAddresses(): void {
    $user = UserFactory::createUser('email-validation-user');
    $token = UserFactory::getJWTTokenForUserId($user->id);
    foreach (["member@example.com\r\n", ['member@example.com']] as $address) {
      $this->json('POST', '/api/v1/user/myself/changeEmailAddresses?token=' . $token, ['email_addresses' => [$address]]);
      $this->assertResponseStatus(422);
    }

    $this->json('POST', '/api/v1/user/myself/changeEmailAddresses?token=' . $token, ['email_addresses' => ['valid+tag@example.com']]);
    $this->assertResponseStatus(200);
    self::assertSame(1, \App\Models\User\UserEmailAddress::where('email', 'valid+tag@example.com')->count());
  }

  public function testModernDatabaseFactoryCreatesUsers(): void {
    $user = \Database\Factories\UserFactory::new()->create();

    self::assertNotNull($user->id);
    self::assertSame(1, \App\Models\User\User::where('username', $user->username)->count());
  }
}
