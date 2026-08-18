# Konekpay WooCommerce Payment Gateway

Plugin pembayaran Konekpay untuk WordPress & WooCommerce. Mendukung pembayaran otomatis berbasis bank transfer Virtual Account (VA), QRIS, dan retail store (Alfamart/Indomaret). Mendukung halaman checkout klasik (Shortcode) maupun Gutenberg Blocks terbaru.

Repository ini berisi kode sumber plugin WooCommerce Konekpay yang dapat di-deploy langsung ke situs WordPress Anda.

## 🚀 Fitur Utama
- **Snap Iframe (Modal Overlay)**: Menampilkan antarmuka pembayaran Konekpay langsung di halaman checkout WooCommerce tanpa redirect penuh browser.
- **Daur Ulang Transaksi (Transaction Reuse)**: Mencegah duplikasi data transaksi untuk nomor invoice yang sama.
- **Gutenberg Blocks Support**: Kompatibel secara native dengan WooCommerce Checkout Block (React) dan Shortcode Klasik (`[woocommerce_checkout]`).
- **File-based Debug Logging**: Mempermudah pelacakan respon pembayaran melalui berkas log internal.

---

## 📦 Cara Instalasi

Anda dapat menginstal plugin ini dengan beberapa metode berikut:

### Metode 1: Melalui Download ZIP GitHub (Direkomendasikan)
1. Unduh repositori ini sebagai berkas ZIP dengan mengeklik tombol **Code** > **Download ZIP** di bagian atas halaman GitHub ini.
2. Ekstrak berkas ZIP tersebut di komputer Anda, lalu pastikan nama foldernya adalah `konekpay`.
3. Kompres kembali folder `konekpay` tersebut menjadi berkas `konekpay.zip`.
4. Masuk ke dashboard WordPress Anda, navigasikan ke menu **Plugins > Add New (Tambah Baru)**.
5. Klik **Upload Plugin (Unggah Plugin)**, pilih berkas `konekpay.zip`, lalu klik **Install Now (Instal Sekarang)**.
6. Klik **Activate Plugin (Aktifkan Plugin)**.

### Metode 2: Menggunakan Git Clone (Bagi Developer)
Jika Anda memiliki akses terminal/SSH ke server WordPress Anda, masuk ke direktori plugin WordPress Anda dan jalankan perintah berikut:
```bash
cd wp-content/plugins/
git clone https://github.com/konekpay/konekpay_woocomerce.git konekpay
```
Setelah proses clone selesai, aktifkan plugin **Konekpay WooCommerce Payment Gateway** melalui dashboard WordPress Anda.

---

## ⚙️ Cara Konfigurasi

1. Buka dashboard WordPress Anda, lalu navigasikan ke menu **WooCommerce > Settings (Pengaturan) > Payments (Pembayaran)**.
2. Temukan metode pembayaran bernama **Konekpay**, kemudian klik tombol **Manage (Kelola)** atau aktifkan switch buttonnya.
3. Konfigurasikan opsi-opsi berikut sesuai dengan kebutuhan:
   - **Enable/Disable**: Centang untuk mengaktifkan Konekpay di halaman checkout.
   - **Title**: Nama metode pembayaran yang tampil di layar checkout pembeli (contoh: *Konekpay (VA, QRIS, Alfamart)*).
   - **Description**: Panduan instruksi singkat pembayaran yang tampil di bawah nama metode.
   - **Sandbox Mode**: Centang "Yes" jika Anda sedang melakukan pengujian integrasi (menggunakan environment Sandbox).
   - **API Key**: Masukkan Konekpay API Key yang Anda peroleh dari dashboard Member Konekpay Anda.
4. Klik **Save Changes (Simpan Perubahan)**.

---

## 🛠️ Panduan Penggunaan & Pengujian

### Alur Checkout Pembeli
1. Pembeli menambahkan produk ke keranjang belanja dan masuk ke halaman **Checkout**.
2. Pada pilihan metode pembayaran, pembeli memilih **Konekpay**, lalu mengklik tombol **Place Order (Buat Pesanan)**.
3. Halaman checkout tidak akan memuat ulang (looping), melainkan langsung memunculkan **Snap Iframe (Modal Popup)** Konekpay di tengah layar.
4. Pembeli dapat memilih saluran pembayaran (misalnya BCA Virtual Account).
5. Layar modal akan langsung menampilkan nomor Virtual Account beserta instruksi transfer.
6. Apabila pembeli tidak sengaja menutup modal dan menekan tombol *Place Order* kembali untuk invoice yang sama, sistem **tidak akan melakukan request ulang ke Doku** (tidak akan memicu error duplikasi invoice). Sistem akan mendeteksi status dan langsung memunculkan informasi Virtual Account yang didapat sebelumnya.
7. Setelah transfer berhasil diselesaikan pembeli, status order di WooCommerce akan otomatis diperbarui menjadi **Processing** melalui Webhook Konekpay.

---

## 📝 Debugging & Logging

Jika Anda menemukan kendala saat melakukan transaksi, Anda dapat memeriksa berkas log sistem untuk melacak aktivitas request API.
- Berkas log tersimpan di dalam folder plugin: `wp-content/plugins/konekpay/debug_log.txt` (atau sesuai nama folder instalasi plugin Anda).
- Log ini merekam seluruh detail respon API, status integrasi, serta payload request/response pembayaran.
