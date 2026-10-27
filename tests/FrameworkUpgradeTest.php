<?php

use App\Jobs\SendEmailJob;
use App\Mail\ForgotPassword;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpImap\Mailbox;
use PHPUnit\Framework\Attributes\DataProvider;

class FrameworkUpgradeTest extends TestCase {
  public static function smtpEncryptionModes(): array {
    return [
      'STARTTLS' => ['tls', false, true],
      'implicit TLS' => ['ssl', true, false],
      'unencrypted' => ['null', false, false],
    ];
  }

  #[DataProvider('smtpEncryptionModes')]
  public function testSmtpPreservesLegacyEncryptionSettings(string $encryption, bool $implicitTls, bool $requireTls): void {
    $previousEnv = $_ENV;
    $previousServer = $_SERVER;
    $previousEncryption = getenv('MAIL_ENCRYPTION');
    $previousScheme = getenv('MAIL_SCHEME');
    unset($_ENV['MAIL_SCHEME'], $_SERVER['MAIL_SCHEME']);
    putenv('MAIL_SCHEME');
    $_ENV['MAIL_ENCRYPTION'] = $_SERVER['MAIL_ENCRYPTION'] = $encryption;
    putenv('MAIL_ENCRYPTION=' . $encryption);

    try {
      $config = require __DIR__ . '/../config/mail.php';
      $transport = $this->app->make('mail.manager')->build($config['mailers']['smtp'])->getSymfonyTransport();
      self::assertSame($implicitTls, $transport->getStream()->isTLS());
      self::assertSame($requireTls, $transport->isTlsRequired());
      self::assertSame($encryption === 'tls', $transport->isAutoTls());
    } finally {
      $_ENV = $previousEnv;
      $_SERVER = $previousServer;
      putenv($previousEncryption === false ? 'MAIL_ENCRYPTION' : 'MAIL_ENCRYPTION=' . $previousEncryption);
      putenv($previousScheme === false ? 'MAIL_SCHEME' : 'MAIL_SCHEME=' . $previousScheme);
    }
  }

  public function testCorsPreflightPreservesApiAccess(): void {
    $this->call('OPTIONS', '/api/v1/management/users', [], [], [], [
      'HTTP_ORIGIN' => 'https://datepoll.example.com',
      'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
      'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
    ]);

    $this->assertResponseStatus(204);
    self::assertSame('*', $this->response->headers->get('Access-Control-Allow-Origin'));
    self::assertStringContainsString('POST', $this->response->headers->get('Access-Control-Allow-Methods'));
  }

  public function testCorsHeadersArePresentOnAuthenticationErrors(): void {
    $this->call('GET', '/api/v1/management/users', [], [], [], [
      'HTTP_ORIGIN' => 'https://datepoll.example.com',
    ]);

    $this->assertResponseStatus(401);
    self::assertSame('*', $this->response->headers->get('Access-Control-Allow-Origin'));
  }

  public function testMailUsesSymfonyTransportAndRendersBothBodies(): void {
    $mailer = Mail::mailer();
    $transport = $mailer->getSymfonyTransport();
    self::assertInstanceOf(ArrayTransport::class, $transport);

    $mailer->to('member@example.com')->send(new ForgotPassword('Test Member', '123456'));

    $message = $transport->messages()->last()->getOriginalMessage();
    self::assertStringContainsString('123456', $message->getHtmlBody());
    self::assertStringContainsString('123456', $message->getTextBody());
    self::assertSame('member@example.com', $message->getTo()[0]->getAddress());
  }

  public function testMailerCanBeSelectedFromConfiguration(): void {
    $config = $this->app->make('config');
    self::assertSame('array', $config->get('mail.default'));
    $config->set('mail.default', 'log');
    self::assertInstanceOf(\Illuminate\Mail\Transport\LogTransport::class, Mail::mailer()->getSymfonyTransport());
  }

  public function testAttachmentStorageRetainsItsExistingRoot(): void {
    $disk = Storage::disk('local');
    $path = 'files/upgrade-test-' . bin2hex(random_bytes(8));

    try {
      self::assertTrue($disk->put($path, 'attachment contents'));
      self::assertSame(storage_path('app/' . $path), $disk->path($path));
      self::assertSame('attachment contents', $disk->get($path));
      self::assertTrue($disk->delete($path));
      self::assertFalse($disk->exists($path));
    } finally {
      $disk->delete($path);
    }
  }

  public function testSynchronousQueueExecutesTheExistingEmailJob(): void {
    Mail::fake();

    Queue::connection('sync')->push(new SendEmailJob(new ForgotPassword('Test Member', '654321'), ['member@example.com']));

    Mail::assertSent(ForgotPassword::class, function (ForgotPassword $mail) {
      return $mail->hasBcc('member@example.com') && $mail->code === '654321';
    });
  }

  public function testRedisQueueRoundTrip(): void {
    if (getenv('TEST_REDIS') !== '1') {
      $this->markTestSkipped('Set TEST_REDIS=1 with Redis available to run this integration test.');
    }

    Mail::fake();
    $queue = Queue::connection('redis');
    $name = 'upgrade-test-' . bin2hex(random_bytes(8));
    try {
      $queue->push(new SendEmailJob(new ForgotPassword('Test Member', '987654'), ['member@example.com']), '', $name);
      self::assertSame(1, $queue->size($name));
      $job = $queue->pop($name);
      self::assertNotNull($job);
      $job->fire();
      $job->delete();
      self::assertSame(0, $queue->size($name));
      Mail::assertSent(ForgotPassword::class);
    } finally {
      $queue->clear($name);
    }
  }

  public function testImapMailboxCanBeConstructedWithTheRuntimeExtension(): void {
    self::assertTrue(extension_loaded('imap'));
    $mailbox = new Mailbox('{imap.example.com:993/imap/ssl}INBOX', 'test', 'test', null, 'UTF-8');
    self::assertInstanceOf(Mailbox::class, $mailbox);
  }

  public function testLogViewerProviderAndProtectedRouteAreAvailable(): void {
    self::assertTrue($this->app->make('view')->exists('laravel-log-viewer::log'));
    $this->get('/logs');
    $this->assertResponseStatus(401);
    $this->seeJsonContains(['error_code' => 'token_not_provided']);
  }
}
