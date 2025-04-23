# Feature Flags Client Wrapper

## Server setup notes for splitd:
wget https://github.com/splitio/splitd/releases/download/v1.6.0/splitd-linux-amd64-1.6.0.bin \
    -O splitd.bin
chmod +x splitd.bin
sudo mkdir -p /opt/splitd
sudo mv ./splitd.bin /opt/splitd/splitd
sudo touch /opt/splitd/splitd.yaml

sudo wget https://raw.githubusercontent.com/splitio/splitd/main/splitd.yaml.tpl \
    -O /opt/splitd/splitd.yaml 
sudo vim /opt/splitd/splitd.yaml
# TODO: 
# 1. REPLACE API KEY WITH ENVIRONMENT APPROPRIATE ONE
# 2. set `address: /opt/splitd/splitd.sock`

sudo vim /etc/systemd/system/splitd.service
```
[Unit]
Description=Split daemon for feature flagging
After=syslog.target network-online.target remote-fs.target nss-lookup.target
Wants=network-online.target

[Service]
Type=simple
User=ec2-user
Group=ec2-user
UMask=002
Environment=SPLITD_CONF_FILE="/opt/splitd/splitd.yaml"
PIDFile=/opt/splitd/splitd.pid
ExecStart=/opt/splitd/splitd
ExecStop=/bin/kill -s QUIT $MAINPID
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

THEN:
```
sudo systemctl daemon-reload
sudo systemctl enable splitd
sudo systemctl start splitd
sudo systemctl status splitd
```
