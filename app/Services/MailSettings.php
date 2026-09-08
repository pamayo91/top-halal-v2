<?php
namespace App\Services;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;

class MailSettings
{
    public function values(): array { return (array) (Setting::where('key', 'mail_settings')->value('value') ?? []); }
    public function apply(): string
    {
        $v = $this->values(); $mailer = $v['mailer'] ?? config('mail.default');
        if ($mailer !== 'smtp' || blank($v['host'] ?? null)) return $mailer;
        Config::set('mail.mailers.smtp', array_filter(['transport'=>'smtp','host'=>$v['host'],'port'=>(int)($v['port'] ?? 587),'scheme'=>($v['encryption'] ?? null) === 'none' ? null : ($v['encryption'] ?? null),'username'=>$v['username'] ?? null,'password'=>filled($v['password'] ?? null) ? Crypt::decryptString($v['password']) : null,'timeout'=>(int)($v['timeout'] ?? 10)], fn($x) => $x !== null));
        Config::set('mail.from', ['address'=>$v['from_address'] ?? config('mail.from.address'),'name'=>$v['from_name'] ?? config('mail.from.name')]);
        return $mailer;
    }
    public function update(array $input): void
    {
        $current = $this->values(); if (blank($input['password'] ?? null)) $input['password'] = $current['password'] ?? null; else $input['password'] = Crypt::encryptString($input['password']);
        Setting::updateOrCreate(['key'=>'mail_settings'], ['group'=>'email','value'=>$input]);
    }
    public function hasPassword(): bool { return filled($this->values()['password'] ?? null); }
}
