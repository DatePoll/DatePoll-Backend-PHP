<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Class ADatePollMailable
 * @property string $jobDescription
 */
abstract class ADatePollMailable extends Mailable {
  use Queueable, SerializesModels;

  public string $jobDescription;

  /**
   * Create a new message instance.
   *
   * @param string $jobDescription
   */
  public function __construct(string $jobDescription) {
    $this->jobDescription = $jobDescription;
  }

  protected function buildFrom($message) {
    $this->assertSafeAddresses('from');

    return parent::buildFrom($message);
  }

  protected function buildRecipients($message) {
    foreach (['to', 'cc', 'bcc', 'replyTo'] as $property) {
      $this->assertSafeAddresses($property);
    }

    return parent::buildRecipients($message);
  }

  private function assertSafeAddresses(string $property): void {
    foreach ($this->{$property} as $recipient) {
      // Reject raw controls before Symfony trims addresses or sanitizes names.
      foreach ([$recipient['address'], $recipient['name'] ?? ''] as $value) {
        if (preg_match('/[\x00-\x1F\x7F]/', (string) $value)) {
          throw new InvalidArgumentException('Email addresses and display names must not contain control characters.');
        }
      }
    }
  }
}
