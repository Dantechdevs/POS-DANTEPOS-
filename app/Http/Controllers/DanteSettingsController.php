<?php

namespace App\Http\Controllers;

use App\Services\AfricasTalkingSms;
use App\Services\DanteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

class DanteSettingsController extends Controller
{
    private function allowed(): void
    {
        if (! auth()->user()->can('dantepos.settings')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index(Request $request)
    {
        $this->allowed();

        $lines = [];
        $counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];
        try {
            $buf = new BufferedOutput();
            Artisan::call('dantepos:check', [], $buf);
            $raw = $buf->fetch();
        } catch (\Throwable $e) {
            $raw = '[ FAIL ] The check could not run: '.$e->getMessage();
        }
        foreach (preg_split('/\r?\n/', $raw) as $l) {
            if (preg_match('/^\[ (OK|WARN|FAIL) \]\s+(.*)$/', $l, $m)) {
                $lines[] = ['level' => strtolower($m[1]), 'text' => $m[2]];
                $counts[strtolower($m[1])]++;
            }
        }

        $settings = DanteSettings::current();

        $dir = config('backup.path') ?: storage_path('app/backups');
        $files = glob($dir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $backups = array_map(fn ($f) => [
            'name' => basename($f),
            'size' => round(filesize($f) / 1024).' KB',
            'time' => date('d/m/Y H:i', filemtime($f)),
        ], array_slice($files, 0, 5));

        $mp = config('services.mpesa');
        $services = [
            'Africa\'s Talking SMS' => (config('africastalking.username') && config('africastalking.api_key')) ? 'Set ('.config('africastalking.env').')' : 'Not set',
            'M-Pesa' => (! empty($mp['consumer_key']) && ! empty($mp['consumer_secret']) && ! empty($mp['shortcode']) && ! empty($mp['passkey'])) ? 'Set ('.($mp['env'] ?? '?').')' : 'Not set',
            'eTIMS' => config('etims.enabled') ? 'On' : 'Off',
            'Backup copy folder' => config('backup.copy_path') ? 'Set' : 'Not set',
        ];

        return view('dantepos_settings.index', compact('lines', 'counts', 'settings', 'backups', 'services'));
    }

    public function update(Request $request)
    {
        $this->allowed();

        $request->validate([
            'price_guard_mode' => 'required|in:off,warn,block',
            'owner_phones' => 'nullable|string|max:300',
        ]);

        $phones = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $request->input('owner_phones')))) as $p) {
            $n = AfricasTalkingSms::normalizePhone($p);
            if (! $n) {
                return back()->withInput()->with('status', ['success' => 0, 'msg' => 'Not a valid Kenyan phone number: '.$p]);
            }
            $phones[] = $n;
        }

        $new = [
            'price_guard_mode' => $request->input('price_guard_mode'),
            'credit_require_phone' => $request->boolean('credit_require_phone') ? '1' : '0',
            'credit_require_limit' => $request->boolean('credit_require_limit') ? '1' : '0',
            'owner_phones' => implode(',', array_unique($phones)),
        ];

        $before = DanteSettings::current();
        foreach ($new as $k => $v) {
            DanteSettings::set($k, $v);
        }

        Log::info('DantePOS settings changed', ['user_id' => auth()->id(), 'before' => $before, 'after' => $new]);

        return back()->with('status', ['success' => 1, 'msg' => 'Settings saved.']);
    }

    public function backupNow()
    {
        $this->allowed();
        @set_time_limit(300);

        $buf = new BufferedOutput();
        $code = Artisan::call('db:backup', ['--no-alert' => true], $buf);
        $out = trim(strip_tags($buf->fetch()));

        return back()->with('status', ['success' => $code === 0 ? 1 : 0, 'msg' => $out ?: ($code === 0 ? 'Backup finished.' : 'Backup failed. See the log.')]);
    }
}
