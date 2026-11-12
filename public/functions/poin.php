<?php

// ========================================
// SISTEM POIN TOKO ONLINE
// ========================================


// ========================================
// ATURAN POIN
// ========================================
// Setiap Rp10.000 = 1 poin

function hitungPoin($total)
{
    $total = (int) $total;

    return intdiv($total, 10000);
}


// ========================================
// TAMBAH POIN
// ========================================

function tambahPoin(
    $pdo,
    $pelanggan_id,
    $jumlah,
    $keterangan,
    $referensi = null
) {

    $pelanggan_id = (int) $pelanggan_id;
    $jumlah = (int) $jumlah;


    // ----------------------------------------
    // Validasi
    // ----------------------------------------

    if ($pelanggan_id <= 0) {

        return false;

    }


    if ($jumlah <= 0) {

        return false;

    }


    // ----------------------------------------
    // CEK DUPLIKASI
    // ----------------------------------------
    // Mencegah poin diberikan dua kali
    // untuk referensi transaksi yang sama.

    if ($referensi !== null) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM riwayat_poin
            WHERE pelanggan_id = ?
            AND referensi = ?
            LIMIT 1
        ");

        $stmt->execute([
            $pelanggan_id,
            $referensi
        ]);


        if ($stmt->fetch()) {

            return false;

        }

    }


    try {

        // ----------------------------------------
        // MULAI TRANSACTION
        // ----------------------------------------

        $pdo->beginTransaction();


        // ----------------------------------------
        // TAMBAH SALDO POIN
        // ----------------------------------------

        $stmt = $pdo->prepare("
            UPDATE pelanggan
            SET poin = poin + ?
            WHERE id = ?
        ");

        $stmt->execute([
            $jumlah,
            $pelanggan_id
        ]);


        // Pastikan pelanggan benar-benar ada
        if ($stmt->rowCount() === 0) {

            $pdo->rollBack();

            return false;

        }


        // ----------------------------------------
        // CATAT RIWAYAT
        // ----------------------------------------

        $stmt = $pdo->prepare("
            INSERT INTO riwayat_poin
            (
                pelanggan_id,
                jumlah,
                tipe,
                keterangan,
                referensi
            )
            VALUES
            (
                ?,
                ?,
                'masuk',
                ?,
                ?
            )
        ");

        $stmt->execute([
            $pelanggan_id,
            $jumlah,
            $keterangan,
            $referensi
        ]);


        // ----------------------------------------
        // SIMPAN
        // ----------------------------------------

        $pdo->commit();


        return true;


    } catch (Exception $e) {

        // ----------------------------------------
        // BATALKAN JIKA ERROR
        // ----------------------------------------

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }

        return false;

    }

}


// ========================================
// KURANGI POIN
// ========================================

function kurangiPoin(
    $pdo,
    $pelanggan_id,
    $jumlah,
    $keterangan,
    $referensi = null
) {

    $pelanggan_id = (int) $pelanggan_id;
    $jumlah = (int) $jumlah;


    // ----------------------------------------
    // Validasi
    // ----------------------------------------

    if ($pelanggan_id <= 0) {

        return false;

    }


    if ($jumlah <= 0) {

        return false;

    }


    // ----------------------------------------
    // CEK DUPLIKASI
    // ----------------------------------------

    if ($referensi !== null) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM riwayat_poin
            WHERE pelanggan_id = ?
            AND referensi = ?
            LIMIT 1
        ");

        $stmt->execute([
            $pelanggan_id,
            $referensi
        ]);


        if ($stmt->fetch()) {

            return false;

        }

    }


    try {

        // ----------------------------------------
        // MULAI TRANSACTION
        // ----------------------------------------

        $pdo->beginTransaction();


        // ----------------------------------------
        // CEK SALDO
        // ----------------------------------------

        $stmt = $pdo->prepare("
            SELECT poin
            FROM pelanggan
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $pelanggan_id
        ]);

        $pelanggan = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$pelanggan) {

            $pdo->rollBack();

            return false;

        }


        // ----------------------------------------
        // CEK POIN CUKUP
        // ----------------------------------------

        if ((int) $pelanggan["poin"] < $jumlah) {

            $pdo->rollBack();

            return false;

        }


        // ----------------------------------------
        // KURANGI SALDO
        // ----------------------------------------

        $stmt = $pdo->prepare("
            UPDATE pelanggan
            SET poin = poin - ?
            WHERE id = ?
        ");

        $stmt->execute([
            $jumlah,
            $pelanggan_id
        ]);


        // ----------------------------------------
        // CATAT RIWAYAT
        // ----------------------------------------

        $stmt = $pdo->prepare("
            INSERT INTO riwayat_poin
            (
                pelanggan_id,
                jumlah,
                tipe,
                keterangan,
                referensi
            )
            VALUES
            (
                ?,
                ?,
                'keluar',
                ?,
                ?
            )
        ");

        $stmt->execute([
            $pelanggan_id,
            $jumlah,
            $keterangan,
            $referensi
        ]);


        // ----------------------------------------
        // SIMPAN
        // ----------------------------------------

        $pdo->commit();


        return true;


    } catch (Exception $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }

        return false;

    }

}