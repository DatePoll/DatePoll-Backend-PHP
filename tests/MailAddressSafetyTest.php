<?php

use App\Jobs\SendEmailJob;
use App\Mail\BroadcastMail;
use App\Mail\ForgotPassword;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;

class MailAddressSafetyTest extends TestCase {
  public static function unsafeAddressFields(): array {
    $cases = [];
    foreach (['from', 'to', 'cc', 'bcc', 'replyTo'] as $property) {
      foreach (["\rmember@example.com", "\nmember@example.com", "member@example.com\r\n", "member\r\n@example.com", "member\0@example.com"] as $index => $address) {
        $cases[$property . '-address-' . $index] = [$property, $address, 'Test Member'];
      }
      foreach (["Test\rMember", "Test\nMember", "Test\r\nBcc: other@example.com", "Test\tMember"] as $index => $name) {
        $cases[$property . '-name-' . $index] = [$property, 'member@example.com', $name];
      }
    }

    return $cases;
  }

  #[DataProvider('unsafeAddressFields')]
  public function testRawAddressFieldsAreRejectedBeforeNormalization(string $property, string $address, string $name): void {
    $mail = new ForgotPassword('Test Member', '123456');
    $mail->{$property} = [['address' => $address, 'name' => $name]];
    $transport = Mail::mailer()->getSymfonyTransport();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('must not contain control characters');
    try {
      Mail::to('recipient@example.com')->send($mail);
    } finally {
      self::assertCount(0, $transport->messages());
    }
  }

  public function testBroadcastFieldsAddedDuringBuildAreChecked(): void {
    $mail = new BroadcastMail('Test broadcast', 'Body', '<p>Body</p>', "Writer\r\nInjected", 'reply@example.com', 'https://datepoll.example.com', '');
    $transport = Mail::mailer()->getSymfonyTransport();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('must not contain control characters');
    try {
      Mail::to('recipient@example.com')->send($mail);
    } finally {
      self::assertCount(0, $transport->messages());
    }
  }

  public function testInvalidSerializedQueueRecipientsPreventAllDelivery(): void {
    $job = new SendEmailJob(new ForgotPassword('Test Member', '123456'), [
      'valid@example.com',
      "invalid@example.com\r\n",
    ]);
    $job = unserialize(serialize($job));
    $transport = Mail::mailer()->getSymfonyTransport();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('must not contain control characters');
    try {
      $job->handle();
    } finally {
      self::assertCount(0, $transport->messages());
    }
  }

  public function testEmailValidationRejectsCrAndLf(): void {
    $factory = $this->app->make('validator');
    foreach (["\rmember@example.com", "member@example.com\n", "member(\r\n comment)@example.com", ['member@example.com']] as $value) {
      self::assertTrue($factory->make(['email' => $value], ['email' => 'email'])->fails());
    }
  }

  public function testOrdinaryInternationalAndLiteralPercentAddressesStillWork(): void {
    $mail = new ForgotPassword('Müller', '123456');
    $mail->from('sender@example.com', 'Müller, Anna');
    $mail->replyTo('reply+tag@example.com', "O'Connor");
    Mail::to(['müller@bücher.example', 'member%0d%0a@example.com'])->send($mail);

    $messages = Mail::mailer()->getSymfonyTransport()->messages();
    self::assertCount(1, $messages);
    $message = $messages->first()->getOriginalMessage();
    self::assertSame('Müller, Anna', $message->getFrom()[0]->getName());
    self::assertSame('müller@bücher.example', $message->getTo()[0]->getAddress());
    self::assertSame('member%0d%0a@example.com', $message->getTo()[1]->getAddress());
    self::assertTrue($this->app->make('validator')->make(['email' => 'member+tag@example.com'], ['email' => 'email'])->passes());
  }
}
