<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SettingEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_secrets_are_encrypted(): void
    {
        $plainToken = 'EAAGz3kXmZA14BA...secret-token-123';
        
        // Save as secret
        Setting::setValue('MY_SECRET_TOKEN', $plainToken, true);

        // Assert database value is NOT equal to plain text (it is encrypted)
        $rawDatabaseRecord = Setting::where('key', 'MY_SECRET_TOKEN')->first();
        $this->assertNotNull($rawDatabaseRecord);
        $this->assertNotEquals($plainToken, $rawDatabaseRecord->value);

        // Verify we can decrypt the database value back to plain text
        $decryptedValue = Crypt::decryptString($rawDatabaseRecord->value);
        $this->assertEquals($plainToken, $decryptedValue);

        // Verify getValue() returns the decrypted plain text
        $retrievedValue = Setting::getValue('MY_SECRET_TOKEN');
        $this->assertEquals($plainToken, $retrievedValue);
    }
}
