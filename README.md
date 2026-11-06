# wordpress-groups-plugin

**WordPress Groups Plugin**, BuddyPress gerektirmeyen, yalnızca tartışma gruplarına
odaklanan bir WordPress eklentisidir. WordPress 6.4+ ve PHP 7.4+ gerektirir.

## Kurulum

1. Bu klasörü `wp-content/plugins/wordpress-groups-plugin` içine yerleştirin.
2. WordPress **Eklentiler** ekranından etkinleştirin.
3. Bir sayfaya `[wordpress_groups]`, ayrı bir sayfaya
   `[wordpress_groups_messages]` kısa kodunu ekleyin ve sayfaları menünüze ekleyin.
4. **Ayarlar → Tartışma grupları** bölümünden grup oluşturabilecek rolleri,
   varsayılan gizlilik/üyelik yöntemini ve özel mesajlaşmayı ayarlayın.

## Gruplar ve yetkiler

- **Herkese açık:** Ziyaretçiler dahil herkes okuyabilir; yazmak için üyelik gerekir.
- **Özel:** Grup adı ve açıklaması dizinde görünür, tartışmaları yalnızca üyeler okur.
- **Gizli:** Dizin ve grup sayfası yalnızca üyelere/davetlilere görünür. Davetliler
  kabul etmeden tartışmaları okuyamaz. Gizli gruplar daima davetle üyelik kullanır.
- Açık üyelik, onaylı üyelik ve yalnızca davetle üyelik desteklenir.
- Grup sahibi ayarları değiştirir, moderatör atar ve grubu silebilir.
  Moderatörler davetleri, üyelik isteklerini, engellemeleri ve tartışmaları yönetir;
  başka moderatörleri yönetemez veya grubun ayarlarını değiştiremez.
- Üyeler gruptan ayrılabilir, isteklerini/davetlerini iptal edebilir. Grup sahibi
  ayrılmak yerine grubu silmelidir. Engellenmiş üyeler yeniden katılamaz;
  yönetimden engelleri kaldırılabilir.
- Konular ve yanıtlar, konu kilitleme, yanıt/konu silme ve yalnızca
  moderatörlerin konu başlatabilmesi ayarı bulunur.
- Site yöneticileri, gizli gruplar dahil tüm grupları yönetebilir. Başlangıçta
  standart WordPress rolleri grup oluşturabilir; rol seçimlerini boş bırakmak
  oluşturmayı yalnızca site yöneticileriyle sınırlar.

## Özel mesajlar

Özel mesajlaşma başlangıçta kapalıdır. Etkinleştirildiğinde üyeler giriş adıyla
birbirine düz metin mesaj gönderebilir, gelen/gönderilen mesajlarını okuyabilir
ve kendi kutularından silebilir. Mesaj iki tarafça da silinince veritabanından
kaldırılır. Her üye yeni mesaj almayı kapatabilir. Gönderim sınırı dakikada
beş mesajdır. Mesajlaşmanın yönetimden kapatılması hem göndermeyi hem posta
kutusuna erişimi durdurur; eski mesajları silmez.

Mesajlar **uçtan uca şifrelenmez**; veritabanına erişimi olan site işletmecileri
okuyabilir. HTTPS kullanın ve site gizlilik politikanızda bu saklamayı açıklayın.
WordPress hesabı silindiğinde o hesabın özel mesajları, üyelikleri ve sahip
olduğu gruplar silinir; diğer gruplardaki yazılarının yazar bilgisi anonimleşir
(yazı metinleri korunur).

## Erişilebilirlik ve işletim

Arayüz JavaScript gerektirmez; etiketli yerel form alanları, başlıklar, klavye ile
kullanılabilen düğmeler, durum mesajları ve sayfalama kullanır. Tema stilleri
devralınır: temanızda görünür klavye odağı, yeterli kontrast ve mobil uyumu
ayrıca doğrulayın. Metinler WordPress çeviri işlevleriyle hazırlanmıştır.

Grup içerikleri ve mesajlar ayrı tablolardadır; WordPress genel arama, REST ve
RSS akışlarında yayımlanmaz. Yazma işlemleri giriş, nonce ve sunucu tarafı
yetki denetimi gerektirir. HTML gönderileri kabul edilmez.

**Kısa kod sayfalarını ve `wp-admin/admin-post.php` yolunu CDN/tam sayfa
önbelleğinizden hariç tutun.** Eklenti önbellek engelleme başlıkları da gönderir,
ancak WordPress çalışmadan yanıt veren bir önbelleği denetleyemez.

Eklentiyi her site için ayrı etkinleştirin; ağ genelinde etkinleştirme ve
BuddyPress veri aktarımı desteklenmez. Devre dışı bırakmak veya eklenti
dosyalarını kaldırmak verileri silmez. Gruplar sahipleri veya site yöneticisi
tarafından grup yönetiminden silinebilir. Veritabanını düzenli yedekleyin.
