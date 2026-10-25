<?php
declare(strict_types=1);

// Liten SMTP-klient (STARTTLS + AUTH LOGIN), nok til å sende e-post via Brevo eller
// en annen SMTP-tjeneste uten eksterne biblioteker. Innstillingene ligger i .env.

function mail_configured(): bool
{
    return env('SMTP_HOST') !== '' && env('MAIL_FROM') !== '';
}

function site_url(string $path = ''): string
{
    return rtrim(env('SITE_URL', 'https://høstmessa.no'), '/') . $path;
}

// Sender en ren tekst-e-post. Kaster RuntimeException hvis noe går galt.
function send_mail(string $to, string $subject, string $body): void
{
    if (!mail_configured()) {
        throw new RuntimeException('E-post er ikke satt opp. Fyll inn SMTP-innstillingene i .env.');
    }
    $host = env('SMTP_HOST');
    $port = (int) env('SMTP_PORT', '587');
    $from = env('MAIL_FROM');
    $fromName = env('MAIL_FROM_NAME', 'Høstmessa');

    $socket = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
    if (!$socket) {
        throw new RuntimeException("Fikk ikke kontakt med e-posttjenesten ($host:$port): $errstr");
    }
    stream_set_timeout($socket, 15);

    $read = function () use ($socket): string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $response;
    };
    $command = function (?string $line, array $expect) use ($socket, $read): string {
        if ($line !== null) {
            fwrite($socket, $line . "\r\n");
        }
        $response = $read();
        if (!in_array((int) substr($response, 0, 3), $expect, true)) {
            $shown = str_starts_with((string) $line, 'AUTH') || $line === null ? '' : ' etter «' . strtok((string) $line, ' ') . '»';
            throw new RuntimeException('E-posttjenesten svarte' . $shown . ': ' . trim($response));
        }
        return $response;
    };

    try {
        $command(null, [220]);
        $ehlo = $command('EHLO ' . (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost'), [250]);
        if ($port !== 465 && stripos($ehlo, 'STARTTLS') !== false) {
            $command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('Klarte ikke å starte kryptert forbindelse til e-posttjenesten.');
            }
            $command('EHLO ' . (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost'), [250]);
        }
        if (env('SMTP_USER') !== '') {
            $command('AUTH LOGIN', [334]);
            $command(base64_encode(env('SMTP_USER')), [334]);
            $command(base64_encode(env('SMTP_PASSWORD')), [235]);
        }
        $command('MAIL FROM:<' . $from . '>', [250]);
        $command('RCPT TO:<' . $to . '>', [250, 251]);
        $command('DATA', [354]);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . substr(strrchr($from, '@'), 1) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
        $command($message . "\r\n.", [250]);
        $command('QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

// Engangslenker for å lage eller bytte passord. Bare en hash av lenken lagres.
function create_password_token(int $userId, int $validMinutes): string
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('DELETE FROM password_tokens WHERE user_id = ?')->execute([$userId]);
    db()->prepare('INSERT INTO password_tokens (token_hash, user_id, expires_at) VALUES (?, ?, NOW() + INTERVAL ? MINUTE)')
        ->execute([hash('sha256', $token), $userId, $validMinutes]);
    return $token;
}

// Returnerer brukeren lenken gjelder for, eller null hvis den er ugyldig eller utløpt.
function find_password_token(string $token): ?array
{
    $stmt = db()->prepare('SELECT u.id, u.name, u.email FROM password_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.expires_at > NOW()');
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

function send_password_link(array $user, bool $welcome): void
{
    $token = create_password_token((int) $user['id'], $welcome ? 7 * 24 * 60 : 60);
    $link = site_url('/komite/?passord=' . $token);
    if ($welcome) {
        $subject = 'Velkommen til messekomiteen på Høstmessa';
        $body = "Hei {$user['name']}!\n\nDu er lagt til som bruker på komitésiden til Høstmessa. Der kan du legge ut varer og bilder til auksjonen.\n\nLag ditt eget passord her (lenken virker i 7 dager):\n$link\n\nDu logger inn med denne e-postadressen: {$user['email']}\n\nHilsen messekomiteen";
    } else {
        $subject = 'Nytt passord til Høstmessa';
        $body = "Hei {$user['name']}!\n\nNoen (forhåpentligvis du) har bedt om å lage nytt passord til komitésiden på Høstmessa.\n\nLag nytt passord her (lenken virker i 1 time):\n$link\n\nHar du ikke bedt om dette, kan du se bort fra e-posten. Passordet ditt er ikke endret.\n\nHilsen messekomiteen";
    }
    send_mail($user['email'], $subject, $body);
}
