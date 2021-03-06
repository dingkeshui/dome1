<?php
class config
{
	//异步通知地址
	const  notify_ur = "https://m.zxty.me/index.php/Api/Pay/goBackAliPay";
	//同步跳转
	const  return_url = "https://m.zxty.me";

	const gatewayUrl = "https://openapi.alipay.com/gateway.do";

	const      appId = "2017061107469300";

	const rsaPrivateKey = 'MIIEogIBAAKCAQEAutAjp9jpqZQL+QooI+cTui+otA8wZbAiGSnP1g2UyPMV0zBWK2Iwlf3EargKsQzgCdYoKskT/4zhto3WoRLbx0aWaZWQhdpm/ARuqdlg+HPMobnkG1VCQ3iT7lxRnLo5SOlri1xyRvG7PU/KmLZx9o3aoaBVM1GbOqnc8elmxQF2BQ4jZMmJ4KGByjxKEpK0UUdpMAhhB8pl7Ery1IDvyZa0Z/LuSNos3Z/43BuZwlp9yxgn0Iiq1pGbOZcjUAgMFHj7i5g087BDeMa9ojY2sC3t36gElSshgFLd6Cc5A/xF31FMf66fT47JBnGhTPsu1nKO6Lyb0v7UhqBpsdjebwIDAQABAoIBAEuajsOQIsiRdOL9/E7DZxVz0kfE2eZdiP88D7PmXS+SSWPl7QmzvEwHnqU+BH2w4z634BuosyI6RuLVtmYLtsyIQblLYWj6gbE+rfapVfhXDRrqm2mAcMfTlbLiw9i0+RgL9404Bbu2evuOPkTdmXOZ50CAHdseDL+KHTP+LKxPgG9vKfwTQUSUTqzRacDTY6+Oo6BgkjbGOlUqbSRze5wZ6kE2De5g4GhaV292Ac6UX2oJZ5Hqe0LeLxo761L5/1ApRmoCGimuav+nQg54GCwEd+rVvrmnRidH+t1DZKriYCnwI6sQcoqUjGBve8W+Ewn1+abB2pIQEL5X4WdyHYECgYEA7aytRQMaClAsSph7KR6EJQYNq45+33Yw0aFzYr29KtEimNUsZid3LzX98xz4dl/FMGVviPjXeFE7Zoxy8XQ6edSpc/LfjDtciwioX5/KVrPsREvxN4f1XAUB01pQugtzrKWY4ipjlBmFKtUh6wVMIU9w1hffdYpHVUXnPJ5uPC8CgYEAyTeJWFoCh/kIIFFiFvEhhV9CjR6xVY/buTAqjrNBl6Ei8Mthqr96iLzoiEg9WbheUxj0uvSqv4OOdTmRcLuU32VrOHUDes67vj9uc+Iup+CRgR9fQvdboOA/CfGRyzCj51EYNxuo8TfNksFSEKs3efA0KEoWjnV6BAWUWyuvscECgYA5VvZ6dtQadtsop/7WncEduYckw30CZl5CxOO0h7gSk1/4ZT2P66eKYUlCFNQwosxM77vn3LkHEvn0tJ+qdvMAn3i+O3MJPu0FsfIFF+TV5BR1KakpT4i3SZH2LozZRZgDp/PpZHafrUR+X+sN6zpBswuy6xGDJvgsr2+pJUPNCwKBgHX+cjT0O22+9Cv3bVzks/KBBQa6C+WPwZpcC2i452riXBn2OeSl+lX66SUZr5hQTEfQZ5rGZOZ/X+eQBaCk70z9LR3jSFIYrvGGQuADr7ZUMx8T5YnggcPaHD1ZglQzxrmbzWZreutQ/OTlJpmNva9s0zkZERci/pUjLHc2LAZBAoGAdU72KY+RxzUbLG4TIKiUYybyO8yVwtm6Qx2Do4q8K/wokG/oHQzDmctO2IeiMkDmXDGJ2RVoocIwa2npc8jx6boVfaF+Pl7WYKQoDoy1r7Zrn8bZVBTg9Mzz4hFScv4/Cl+FrE4DkCeaO8MocxCZS5uqBovqeMP57c/nMcG4nMA=';

	const format = "json";

	const charset = "UTF-8";

	const signType = "RSA2";

	const alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAlOnK5GeuSbNuAy7H4C9nRVipAdDJ+IFayl1E2YDf4LYA2bPG1cdSx0aVkUNj3yM83pqYj8ZxNqzV/Y6OfAHmmMKe3vASERsZsqfCZfytwQCdQbOvRZISc95djVwPBGkBGWjBwgNnLKPQepbxJqUBIaUPwN1kE/lI26f4APEaLtlse2MQdCS92iud/58dCKW4HNSFUO3tpV0FglS9s/2h0CyLQ4a2xU9JzNonDhydIpCPpvFB1kZEDXPDgVTW7wG5T+k6Xv/WyR2jkldc+ERL3BpZb4Jw+CdNTmR6zu1ANpY5ov8/x1PZlYPyGsgEwWOoqYtyB7ph+zhKhIFRwULU/QIDAQAB';
}