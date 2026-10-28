# Meta Admin - Bao cao quang cao theo ngay

Duong dan muc tieu khi deploy nhanh nay len hosting: `https://mooz.gt.tc/meta/admin/`
Khong cham vao `/admin/` cu cua shop.

## Cai dat tren hosting (PHP 8, pdo_sqlite, curl, openssl)

1. Deploy nhanh `meta-admin` nay len `public_html/meta/` sao cho co `public_html/meta/admin/index.php`.
2. Cho phep ghi thu muc `meta/admin/data/` (chmod 755, neu loi thi 775).
3. Mo `https://mooz.gt.tc/meta/admin/install.php`, tao tai khoan admin.
4. **Xoa `install.php` ngay.**
5. Dang nhap, admin vao **Nguoi dung** tao tai khoan cho tung nhan vien.
6. Moi nhan vien vao **Tai khoan QC**, them tung tai khoan quang cao minh dang chay:
   vi du A: 1,2,3; B: 2,3,4; C: 3,4,1. Tai khoan trung nhau thi ca 2 deu thay camp,
   nhung token moi nguoi giu rieng va duoc ma hoa truoc khi luu.
7. Vao **Dong bo Meta** de lay insights tu Meta Graph API v21.0.
   Token can quyen `ads_read`. Neu token het han hoac thieu quyen, app se bao ro ma khong hien token.
8. Vao **Camp** gan ma san pham cho tung camp, vao **Doanh so** nhap don / doanh thu /
   gia von / van chuyen / chi phi khac theo ngay, theo san pham, theo nguoi.
9. Xem **Tong hop** (hom nay / hom qua / hom kia / tuan nay / tuan truoc / thang nay),
   **Theo ngay**, **San pham**, **Danh gia AI**, va **Xuat Sheet (CSV)** de dua vao Google Sheet.

## 12 chi so giong Sheet BC_MKT

Chi phi Marketing, Don, San pham, Doanh so, Ty le hoan du kien, Doanh thu du kien,
Chi phi/Doanh thu du kien, Chi phi gia von, Chi phi van chuyen, Chi phi khac,
Loi nhuan, Loi nhuan/DT. Them chi so quang cao: Hien thi, Click, CTR, CPC.

Cong thuc:
- Doanh thu du kien = Doanh so * (1 - Ty le hoan du kien)
- Loi nhuan = Doanh thu du kien - Chi phi Marketing - Gia von - Van chuyen - Chi phi khac

## Bao mat - doc ky

- Repo WEBTesst la **public**. Khong bao gio commit token Meta, mat khau, file `data/meta.sqlite`
  hay `data/.key` len GitHub. Token chi nhap qua giao dien web sau khi deploy, luu ma hoa AES-256-CBC.
- Nguoi dung chi thay tai khoan QC cua minh; admin moi thay tat ca.
- Dong bo chi goi API khi bam nut, khong goi moi lan mo trang.
- Danh gia AI hien tai la luat (chi phi/doanh thu, loi nhuan, CTR, CPC, xu huong),
  khong goi LLM ben ngoai, khong can API key.

## Google Sheet tu dong - chua tu dong hoan toan

Ban hien tai xuat CSV dung dinh dang Sheet, import vao Google Sheet bang File > Import.
De dong bo truc tiep vao Sheet can them Service Account Google + ID Sheet dich,
cau hinh nay chua co trong ban nay vi can thong tin tai khoan Google cua ban.
