<?php
// Fungsi bantu yang dipakai di banyak halaman.

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($amount): string
{
    return "Rp" . number_format((float) $amount, 0, ',', '.');
}

// Pesan sekali tampil (disimpan di session, tampil setelah redirect).
function flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function statusLabel(string $status): string
{
    $labels = [
        'pending'    => 'Menunggu',
        'processing' => 'Diproses',
        'completed'  => 'Selesai',
    ];

    return $labels[$status] ?? $status;
}