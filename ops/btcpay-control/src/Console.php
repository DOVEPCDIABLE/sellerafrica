<?php
declare(strict_types=1);

namespace BTCPayControl;

use OTPHP\TOTP;
use PDO;
use RuntimeException;

final class Console
{
    public readonly PDO $db;

    public function __construct(private readonly string $privateDir)
    {
        $this->db = new PDO('sqlite:' . $privateDir . '/console.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('PRAGMA busy_timeout = 5000');
        $this->db->exec('CREATE TABLE IF NOT EXISTS owner (id INTEGER PRIMARY KEY CHECK(id=1), username TEXT NOT NULL, password_hash TEXT NOT NULL, phrase_hash TEXT NOT NULL, totp_secret TEXT NOT NULL, last_step INTEGER NOT NULL DEFAULT -1)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY, created INTEGER NOT NULL, ip TEXT NOT NULL, action TEXT NOT NULL, detail TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS attempts (created INTEGER NOT NULL, ip TEXT NOT NULL, bucket TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS control (id INTEGER PRIMARY KEY CHECK(id=1), restart_at INTEGER NOT NULL DEFAULT 0)');
        $this->db->exec('INSERT OR IGNORE INTO control (id) VALUES (1)');
    }

    public function owner(): array|false
    {
        return $this->db->query('SELECT * FROM owner WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    }

    public function throttle(string $bucket, string $ip): void
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM attempts WHERE bucket=? AND created>? AND (ip=? OR ?=1)');
            $stmt->execute([$bucket, time() - 900, $ip, 0]);
            $ipCount = (int)$stmt->fetchColumn();
            $stmt->execute([$bucket, time() - 900, $ip, 1]);
            if ($ipCount >= 5 || (int)$stmt->fetchColumn() >= 20) throw new RuntimeException('Too many attempts. Please wait 15 minutes.');
            $this->db->prepare('INSERT INTO attempts VALUES (?, ?, ?)')->execute([time(), $ip, $bucket]);
            $this->db->prepare('DELETE FROM attempts WHERE created<?')->execute([time() - 86400]);
            $this->db->exec('COMMIT');
        } catch (\Throwable $error) {
            $this->db->exec('ROLLBACK');
            throw $error;
        }
    }

    public static function matchingStep(string $secret, string $code, ?int $now = null): ?int
    {
        if (!preg_match('/^\d{6}$/D', $code)) return null;
        $now ??= time();
        $otp = TOTP::createFromSecret($secret);
        foreach ([0, -30, 30] as $offset) {
            if ($otp->verify($code, $now + $offset)) return intdiv($now + $offset, 30);
        }
        return null;
    }

    public function claimCode(array $owner, string $code): bool
    {
        $step = self::matchingStep($owner['totp_secret'], $code);
        if ($step === null) return false;
        $stmt = $this->db->prepare('UPDATE owner SET last_step=? WHERE id=1 AND last_step<?');
        $stmt->execute([$step, $step]);
        return $stmt->rowCount() === 1;
    }

    public function enroll(array $pending, string $code): void
    {
        if ($this->owner()) throw new RuntimeException('This console has already been configured.');
        $step = self::matchingStep($pending['secret'], $code);
        if ($step === null) throw new RuntimeException('Enter the six-digit code from your authenticator app.');
        $this->db->prepare('INSERT INTO owner VALUES (1, ?, ?, ?, ?, ?)')->execute([$pending['username'], $pending['password_hash'], $pending['phrase_hash'], $pending['secret'], $step]);
    }

    public function audit(string $action, string $detail, string $ip): void
    {
        $this->db->prepare('INSERT INTO events (created,ip,action,detail) VALUES (?,?,?,?)')->execute([time(), $ip, $action, substr($detail, 0, 500)]);
        $this->db->exec('DELETE FROM events WHERE id NOT IN (SELECT id FROM events ORDER BY id DESC LIMIT 1000)');
    }

    public function restart(): array
    {
        $stmt = $this->db->prepare('UPDATE control SET restart_at=? WHERE id=1 AND restart_at<?');
        $stmt->execute([time(), time() - 300]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('A restart was requested recently. Wait five minutes and refresh health first.');
        return $this->run('restart');
    }

    public function run(string $action, array $input = []): array
    {
        if (!in_array($action, ['health', 'restart', 'terminal-open', 'terminal-close'], true)) throw new RuntimeException('Unsupported operation.');
        if (!function_exists('proc_open')) throw new RuntimeException('Maintenance command execution is unavailable.');
        $process = proc_open(['/usr/bin/sudo', '-n', '/usr/local/sbin/btcpay-control', $action], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Unable to contact the maintenance service.');
        if ($input !== []) fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $started = microtime(true);
        do {
            $output .= stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (strlen($output) > 200000 || microtime(true) - $started > 25) {
                proc_terminate($process);
                fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
                throw new RuntimeException('Diagnostics timed out. Retry shortly or use SSH.');
            }
            if ($status['running']) usleep(50000);
        } while ($status['running']);
        $output .= stream_get_contents($pipes[1]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        $result = json_decode($output, true);
        if (!is_array($result) || !($result['ok'] ?? false)) throw new RuntimeException('The maintenance service could not complete this action. Check health or use SSH.');
        return $result;
    }
}
