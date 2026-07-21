# Подготовка VPS для Docker-деплоя

Этот гайд описывает подготовку нового VPS к production-деплою приложения через Docker Compose. После выполнения сервер будет иметь отдельного пользователя для деплоя, вход по SSH-ключу, автоматические security-обновления, Docker Engine с Compose plugin, директорию приложения и внешний Nginx для HTTPS.

Сценарий рассчитан на Ubuntu Server 24.04 LTS.

Сам build, настройка `.env`, запуск контейнеров и миграции описаны отдельно в [Docker deploy guide](docker-deploy.md).

## Что понадобится

До начала подготовки получите у VPS-провайдера:

- публичный IPv4-адрес сервера;
- root-пароль или доступ под стартовым пользователем с `sudo`;
- доступ к web-консоли или rescue mode на случай ошибки в SSH;
- возможность управлять DNS домена;
- SSH-ключ администратора на локальном компьютере.

Для небольшого production-инстанса Laravel, PostgreSQL, Nginx и queue worker разумная стартовая конфигурация — 2 vCPU, 4 GB RAM и 30–40 GB SSD. Реальные требования зависят от трафика, размера базы и пользовательских файлов. На сервере с 2 GB RAM особенно желательно настроить swap и следить за памятью во время сборки образов.

Все значения в угловых скобках нужно заменить своими:

```text
<server-ip>          публичный IP сервера
<deploy-user>        системный пользователь, например deploy
<domain>             основной домен, например api.example.com
<repository-url>     URL Git-репозитория
```

## 1. Первый вход и обновление системы

Подключитесь к серверу под пользователем, выданным провайдером:

```bash
ssh root@<server-ip>
```

В cloud-образах Ubuntu вместо `root` часто используется пользователь `ubuntu` или другой пользователь из панели провайдера.

Проверьте дистрибутив и архитектуру:

```bash
cat /etc/os-release
uname -m
```

Установите обновления и базовые утилиты:

```bash
sudo apt update
sudo apt full-upgrade -y
sudo apt install -y ca-certificates curl git unattended-upgrades nginx certbot python3-certbot-nginx
```

Если система сообщает, что требуется перезагрузка, выполните ее до продолжения:

```bash
if [ -f /var/run/reboot-required ]; then
    cat /var/run/reboot-required
    sudo reboot
fi
```

После перезагрузки подключитесь снова.

## 2. Пользователь для деплоя

Не используйте `root` для повседневного деплоя. Создайте отдельного пользователя:

```bash
sudo adduser <deploy-user>
sudo usermod -aG sudo <deploy-user>
```

На локальном компьютере создайте SSH-ключ, если его еще нет:

```bash
ssh-keygen -t ed25519 -a 100
```

Скопируйте публичный ключ на сервер:

```bash
ssh-copy-id <deploy-user>@<server-ip>
```

Если `ssh-copy-id` недоступен, добавьте содержимое локального файла `~/.ssh/id_ed25519.pub` в `/home/<deploy-user>/.ssh/authorized_keys` через консоль провайдера и задайте права:

```bash
sudo chown -R <deploy-user>:<deploy-user> /home/<deploy-user>/.ssh
sudo chmod 700 /home/<deploy-user>/.ssh
sudo chmod 600 /home/<deploy-user>/.ssh/authorized_keys
```

Не закрывая текущую сессию, откройте второй терминал и убедитесь, что новый вход работает:

```bash
ssh <deploy-user>@<server-ip>
sudo whoami
```

Последняя команда должна вывести `root` после запроса пароля пользователя.

## 3. Защита SSH

Отключайте root-вход и вход по паролю только после успешной проверки ключа во второй SSH-сессии. Иначе доступ придется восстанавливать через web-консоль провайдера.

Создайте отдельный конфигурационный snippet:

```bash
sudoedit /etc/ssh/sshd_config.d/00-hardening.conf
```

Добавьте:

```text
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
```

Имя начинается с `00-`, чтобы эти значения читались раньше других snippets: для большинства параметров OpenSSH применяется первое найденное значение.

Проверьте синтаксис и итоговую конфигурацию:

```bash
sudo sshd -t
sudo sshd -T | grep -E 'permitrootlogin|passwordauthentication|kbdinteractiveauthentication|pubkeyauthentication'
```

Если ошибок нет и значения соответствуют ожидаемым, перечитайте конфигурацию без обрыва текущих соединений:

```bash
sudo systemctl reload ssh
```

Еще раз проверьте вход по ключу в новой сессии, прежде чем закрывать старую.

## 4. Публикация Docker-портов

В `docker-compose.yml` публикуйте HTTP-порт приложения только на loopback-интерфейсе хоста. Публичный трафик будет принимать внешний Nginx:

```yaml
ports:
    - "127.0.0.1:8080:80"
```

Не используйте для базы публикацию вида `5432:5432`, если удаленный доступ к PostgreSQL не является отдельным осознанным требованием. Внутри compose-сети сервисы доступны друг другу без публикации портов на хосте.

## 5. Автоматические security-обновления

В Ubuntu Server пакет `unattended-upgrades` обычно уже включен и ежедневно устанавливает security-обновления. Проверьте состояние:

```bash
systemctl status unattended-upgrades --no-pager
grep -R "APT::Periodic::Unattended-Upgrade" /etc/apt/apt.conf.d/
```

Если автоматические обновления отключены, включите их:

```bash
sudo dpkg-reconfigure --priority=low unattended-upgrades
```

Автоматическую перезагрузку production-сервера лучше не включать без согласованного maintenance window. Проверяйте необходимость перезагрузки вручную:

```bash
cat /var/run/reboot-required 2>/dev/null
```

Сторонний репозиторий Docker не обязательно входит в источники `unattended-upgrades`. Планово выполняйте `sudo apt update && sudo apt upgrade` и проверяйте release notes перед крупными обновлениями Docker.

## 6. Установка Docker Engine и Compose

Устанавливайте Docker из официального apt-репозитория, а не через convenience script. Удалите конфликтующие пакеты, если провайдер добавил их в образ:

```bash
sudo apt remove -y docker.io docker-compose docker-compose-v2 docker-doc podman-docker containerd runc
```

Если apt сообщает, что пакеты не установлены, это нормально.

Добавьте официальный ключ и репозиторий Docker:

```bash
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
```

```bash
sudo tee /etc/apt/sources.list.d/docker.sources > /dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF
```

Установите Docker Engine, Buildx и Compose plugin:

```bash
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
```

Добавьте deploy-пользователя в группу `docker`:

```bash
sudo usermod -aG docker <deploy-user>
```

Членство в группе `docker` фактически дает root-доступ к серверу. Добавляйте в нее только доверенных администраторов. Выйдите из SSH и подключитесь заново, чтобы применилось членство в группе.

Проверьте установку уже под deploy-пользователем:

```bash
docker version
docker compose version
docker run --rm hello-world
systemctl is-enabled docker
systemctl is-active docker
```

Команда Compose должна иметь вид `docker compose`, без дефиса.

## 7. Ограничение размера Docker-логов

Без ротации json-логи контейнеров могут занять весь диск. Проверьте, существует ли `/etc/docker/daemon.json`:

```bash
sudo test -f /etc/docker/daemon.json && sudo cat /etc/docker/daemon.json
```

Если файла нет, создайте его:

```bash
sudoedit /etc/docker/daemon.json
```

Минимальная конфигурация:

```json
{
    "log-driver": "json-file",
    "log-opts": {
        "max-size": "10m",
        "max-file": "5"
    }
}
```

Если файл уже есть, добавьте параметры в существующий JSON, не затирая настройки провайдера. Проверьте конфигурацию и перезапустите Docker до запуска production-контейнеров:

```bash
sudo dockerd --validate --config-file=/etc/docker/daemon.json
sudo systemctl restart docker
```

Эти значения по умолчанию применяются к новым контейнерам. Для уже созданных контейнеров потребуется их пересоздание.

## 8. Swap для небольшого VPS

Проверьте память и существующий swap:

```bash
free -h
swapon --show
```

Если swap отсутствует и у VPS мало RAM, можно создать swap-файл на 2 GB:

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Проверьте результат:

```bash
swapon --show
free -h
```

Swap страхует от кратковременных пиков памяти, но не заменяет достаточный объем RAM. На VPS с provider-managed swap или нестандартной файловой системой сначала сверьтесь с документацией провайдера.

## 9. Директория приложения и доступ к Git

Создайте директорию приложения под deploy-пользователем:

```bash
sudo mkdir -p /var/www/klinomania-backend
sudo chown <deploy-user>:<deploy-user> /var/www/klinomania-backend
```

Для публичного репозитория достаточно HTTPS URL. Для приватного репозитория предпочтителен отдельный read-only deploy key, добавленный в настройках репозитория. Не копируйте на VPS личный приватный SSH-ключ администратора.

Создать deploy key на сервере можно так:

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -t ed25519 -f ~/.ssh/klinomania_deploy -C "klinomania production deploy"
cat ~/.ssh/klinomania_deploy.pub
```

Добавьте выведенный публичный ключ в Git-хостинг с правом только на чтение. Затем откройте `~/.ssh/config` и настройте alias хоста, чтобы Git использовал этот ключ:

```sshconfig
Host klinomania-git
    HostName <git-host>
    User git
    IdentityFile ~/.ssh/klinomania_deploy
    IdentitiesOnly yes
```

Задайте права и один раз подключитесь к alias, внимательно сверив показанный fingerprint ключа Git-хостинга с его официальной документацией:

```bash
chmod 600 ~/.ssh/config ~/.ssh/klinomania_deploy
ssh -T klinomania-git
```

После проверки клонируйте репозиторий, используя alias вместо реального Git-хоста в SSH URL:

```bash
git clone git@klinomania-git:<owner>/<repository>.git /var/www/klinomania-backend
cd /var/www/klinomania-backend
git checkout main
```

Для production лучше выбирать зафиксированный tag или commit. После создания `.env` ограничьте доступ к нему:

```bash
chmod 600 /var/www/klinomania-backend/.env
```

## 10. DNS

В панели DNS создайте:

- `A`-запись `<domain>` на IPv4 сервера;
- `AAAA`-запись только если IPv6 действительно настроен и доступен на сервере;
- при необходимости `www` как `CNAME` на основной домен или отдельную `A`/`AAAA`-запись.

Не публикуйте `AAAA`, указывающую на неработающий IPv6: часть клиентов будет получать таймауты.

Проверьте DNS со своего компьютера или сервера:

```bash
getent ahosts <domain>
```

Продолжайте выпуск сертификата только когда домен возвращает публичный IP этого VPS, а Nginx доступен из интернета по портам `80` и `443`.

## 11. Host Nginx и HTTPS

В базовой схеме host Nginx принимает публичный HTTP/HTTPS, а compose-сервис `nginx` слушает только `127.0.0.1:8080`. До запуска контейнеров убедитесь, что в `docker-compose.yml` указана публикация:

```yaml
ports:
    - "127.0.0.1:8080:80"
```

Создайте конфигурацию внешнего Nginx:

```bash
sudoedit /etc/nginx/sites-available/klinomania-backend
```

Добавьте:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name <domain>;

    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Включите сайт и проверьте конфигурацию:

```bash
sudo ln -s /etc/nginx/sites-available/klinomania-backend /etc/nginx/sites-enabled/klinomania-backend
sudo unlink /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl enable --now nginx
sudo systemctl reload nginx
```

Удаляйте `default` только если на сервере нет других сайтов, которым он нужен. Ошибка `502 Bad Gateway` до запуска compose-проекта ожидаема: на `127.0.0.1:8080` пока нет приложения.

Выпустите сертификат Let's Encrypt и включите редирект на HTTPS:

```bash
sudo certbot --nginx -d <domain> --redirect
```

Проверьте автоматическое продление:

```bash
systemctl status certbot.timer --no-pager
sudo certbot renew --dry-run
```

После выпуска сертификата проверьте, что Certbot сохранил proxy headers и `proxy_pass` в итоговой Nginx-конфигурации:

```bash
sudo nginx -T
```

## 12. Финальная проверка готовности

Проверьте сервисы и доступные порты:

```bash
sudo systemctl --failed
sudo ss -lntup
docker version
docker compose version
df -h
free -h
```

До запуска приложения снаружи должны быть доступны только SSH, HTTP и HTTPS. После запуска compose-проекта порт `8080` должен отображаться как `127.0.0.1:8080`, а не `0.0.0.0:8080` или `[::]:8080`.

Проверьте привязку отдельно:

```bash
sudo ss -lntp | grep ':8080'
```

С локального компьютера:

```bash
ssh <deploy-user>@<server-ip>
curl -I http://<domain>
curl -I https://<domain>
```

После этого переходите к [Docker deploy guide](docker-deploy.md): создайте production `.env`, соберите образы, запустите compose-проект, выполните миграции и smoke-check.

## Обслуживание сервера

Минимальный регулярный регламент:

- устанавливать системные и Docker-обновления в согласованное окно;
- проверять `df -h`, `free -h`, `docker system df` и рост логов;
- контролировать `systemctl --failed` и `docker compose ps`;
- хранить PostgreSQL backup, пользовательские файлы и `.env` вне этого VPS;
- периодически проверять восстановление из backup;
- проверять срок и автоматическое продление TLS-сертификата;
- удалять SSH-ключи сотрудников, которым доступ больше не нужен;
- перед перезагрузкой убеждаться, что Docker включен в автозапуск и у контейнеров настроена restart policy.

Не запускайте `docker system prune --volumes` на production: команда может удалить неиспользуемые в текущий момент volumes вместе с данными.

## Минимальный checklist

Перед первым Docker-деплоем:

- Ubuntu обновлена, pending reboot отсутствует;
- вход под deploy-пользователем по SSH-ключу работает;
- root-вход и парольная SSH-аутентификация отключены;
- web-консоль или rescue mode провайдера доступны;
- Docker Engine, Buildx и Compose plugin установлены;
- deploy-пользователь может выполнять `docker compose`;
- ротация Docker-логов настроена;
- директория `/var/www/klinomania-backend` принадлежит deploy-пользователю;
- приватный репозиторий доступен через отдельный read-only deploy key;
- DNS указывает на VPS;
- compose-порт приложения будет привязан к `127.0.0.1:8080`;
- внешний Nginx и HTTPS настроены;
- свободного места и памяти достаточно для первой сборки;
- план внешних backup подготовлен.
