#!/bin/bash
cd /data/www/tcpserver01/bin;
echo '正在停止tcp服务器'
./wlxs stop;
sleep 2;
echo '服务器已关闭'
echo '正在启动tcp服务器'
./wlxs start;
echo '正在停止listener'
wlxslistener stop;
echo 'listener已关闭'
wlxslistener start;
echo 'listener已启动'


