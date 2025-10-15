#!/bin/bash

cat > /etc/nginx/sites-available/cool.micronet.mv << 'ENDCONFIG'
server {
    server_name cool.micronet.mv;
    root /var/www/micro-cool;
    index index.html index.htm;
    add_header X-Content-Type-Options nosniff;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~* \.(css|js|png|jpeg|jpg|gif|svg|webp|ico|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        try_files $uri =404;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(env|git) {
        deny all;
        return 404;
    }

    listen [::]:443 ssl ipv6only=on;
    listen 443 ssl;
    ssl_certificate /etc/letsencrypt/live/cool.micronet.mv/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/cool.micronet.mv/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
}

server {
    listen 80;
    listen [::]:80;
    server_name cool.micronet.mv;
    return 301 https://$host$request_uri;
}
ENDCONFIG

ln -sf /etc/nginx/sites-available/cool.micronet.mv /etc/nginx/sites-enabled/cool.micronet.mv
nginx -t && systemctl reload nginx
