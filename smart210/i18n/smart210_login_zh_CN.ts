<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.1" language="zh_CN">
<context>
    <name>FirstSyncPage</name>
    <message>
        <location filename="../ui/FirstSyncPage.ui" line="13"/>
        <source>First download</source>
        <translation>首次下载</translation>
    </message>
    <message>
        <location filename="../ui/FirstSyncPage.ui" line="18"/>
        <source>The terminal needs its users, prices and tickets from the server before it can be used.</source>
        <translation>终端必须先从服务器下载用户、价格和油票，才能使用。</translation>
    </message>
    <message>
        <location filename="../ui/FirstSyncPage.ui" line="29"/>
        <source>Retry</source>
        <translation>重试</translation>
    </message>
    <message>
        <location filename="../src/FirstSyncPage.cpp" line="28"/>
        <source>Downloading from the server... please wait.</source>
        <translation>正在从服务器下载...请稍候。</translation>
    </message>
    <message>
        <location filename="../src/FirstSyncPage.cpp" line="35"/>
        <source>The download did not finish: %1</source>
        <translation>下载未完成：%1</translation>
    </message>
</context>
<context>
    <name>LoginPage</name>
    <message>
        <location filename="../ui/LoginPage.ui" line="13"/>
        <location filename="../ui/LoginPage.ui" line="35"/>
        <source>Login</source>
        <translation>登录</translation>
    </message>
    <message>
        <location filename="../ui/LoginPage.ui" line="18"/>
        <source>Choose your name and type your password.</source>
        <translation>请选择您的姓名并输入密码。</translation>
    </message>
    <message>
        <location filename="../ui/LoginPage.ui" line="29"/>
        <source>Password</source>
        <translation>密码</translation>
    </message>
    <message>
        <location filename="../ui/LoginPage.ui" line="67"/>
        <source>Settings</source>
        <translation>设置</translation>
    </message>
    <message>
        <location filename="../src/LoginPage.cpp" line="51"/>
        <source>There are no cashier accounts on this terminal. Add one in the web app; it arrives with the next download.</source>
        <translation>本终端没有收银员账号。请在网页端添加，下次下载时会同步过来。</translation>
    </message>
    <message>
        <location filename="../src/LoginPage.cpp" line="68"/>
        <source>Choose your name first.</source>
        <translation>请先选择您的姓名。</translation>
    </message>
    <message>
        <location filename="../src/LoginPage.cpp" line="78"/>
        <source>Wrong password.</source>
        <translation>密码错误。</translation>
    </message>
</context>
<context>
    <name>SetupFlow</name>
    <message>
        <location filename="../src/SetupFlow.cpp" line="29"/>
        <source>Enter the server address.</source>
        <translation>请输入服务器地址。</translation>
    </message>
    <message>
        <location filename="../src/SetupFlow.cpp" line="34"/>
        <source>Enter a port from 1 to 65535.</source>
        <translation>请输入 1 到 65535 之间的端口。</translation>
    </message>
    <message>
        <location filename="../src/SetupFlow.cpp" line="62"/>
        <source>The server is reachable, but this terminal&apos;s address (%1) is not registered. Ask the admin to add it on the Devices screen.</source>
        <translation>服务器可以连接，但本终端的地址（%1）尚未登记。请管理员在“设备”页面添加。</translation>
    </message>
    <message>
        <location filename="../src/SetupFlow.cpp" line="74"/>
        <source>The server address could not be saved on this device.</source>
        <translation>服务器地址无法保存到本机。</translation>
    </message>
    <message>
        <location filename="../src/SetupFlow.cpp" line="79"/>
        <source>Connected as %1. Server time %2.</source>
        <translation>已连接，本终端为 %1。服务器时间 %2。</translation>
    </message>
</context>
<context>
    <name>SetupPage</name>
    <message>
        <location filename="../ui/SetupPage.ui" line="13"/>
        <source>Server Setting</source>
        <translation>服务器设置</translation>
    </message>
    <message>
        <location filename="../ui/SetupPage.ui" line="18"/>
        <source>Enter the address of the web app and test the connection. The server must know this terminal&apos;s IP address: the admin adds it on the Devices screen.</source>
        <translation>输入网页端的地址并测试连接。服务器必须登记了本终端的 IP 地址：由管理员在“设备”页面添加。</translation>
    </message>
</context>
</TS>
