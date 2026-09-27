<?php

namespace App\Console\Commands;

use App\Mail\VerificationCodeMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email : The recipient email address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email via Gmail SMTP to verify configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $this->info("Attempting to send test email to: {$email}");
        $this->info("Mailer: ".config('mail.default'));
        $this->info("Host: ".config('mail.mailers.smtp.host'));
        $this->info("Port: ".config('mail.mailers.smtp.port'));
        $this->info("Username: ".config('mail.mailers.smtp.username'));
        $this->info("From Address: ".config('mail.from.address'));

        try {
            Mail::to($email)->send(new VerificationCodeMail(
                code: '123456',
                userName: 'Test User'
            ));
            $this->info("✅ Email successfully sent to {$email}!");
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: ".$e->getMessage());
            return 1;
        }
    }
}
