<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.1" language="zh_TW">
<context>
    <name>FirstSyncPage</name>
    <message>
        <location filename="../ui/FirstSyncPage.ui" line="13"/>
        <source>First download</source>
        <translation>首次下載</translation>
    </message>
    <message>
        <location filename="../ui/FirstSyncPage.ui" line="18"/>
        <source>The terminal needs its users, prices and tickets from the server before it can be used.</source>
        <translation>終端必須先從伺服器下載使用者、價格和油票，才能使用。</translation>
    </message>
    <message>
        <location filename="../ui/FirstSyncPage.ui" line="29"/>
        <source>Retry</source>
        <translation>重試</translation>
    </message>
    <message>
        <location filename="../src/FirstSyncPage.cpp" line="28"/>
        <source>Downloading from the server... please wait.</source>
        <translation>正在從伺服器下載...請稍候。</translation>
    </message>
    <message>
        <location filename="../src/FirstSyncPage.cpp" line="35"/>
        <source>The download did not finish: %1</source>
        <translation>下載未完成：%1</translation>
    </message>
</context>
<context>
    <name>LoginPage</name>
    <message>
        <location filename="../ui/LoginPage.ui" line="13"/>
        <location filename="../ui/LoginPage.ui" line="35"/>
        <source>Login</source>
        <translation>登入</translation>
    </message>
    <message>
        <location filename="../ui/LoginPage.ui" line="18"/>
        <source>Choose your name and type your password.</source>
        <translation>請選擇您的姓名並輸入密碼。</translation>
    </message>
    <message>
        <location filename="../ui/LoginPage.ui" line="29"/>
        <source>Password</source>
        <translation>密碼</translation>
    </message>
    <message>
        <location filename="../ui/LoginPage.ui" line="67"/>
        <source>Settings</source>
        <translation>設定</translation>
    </message>
    <message>
        <location filename="../src/LoginPage.cpp" line="51"/>
        <source>There are no cashier accounts on this terminal. Add one in the web app; it arrives with the next download.</source>
        <translation>本終端沒有收銀員帳號。請在網頁端新增，下次下載時會同步過來。</translation>
    </message>
    <message>
        <location filename="../src/LoginPage.cpp" line="68"/>
        <source>Choose your name first.</source>
        <translation>請先選擇您的姓名。</translation>
    </message>
    <message>
        <location filename="../src/LoginPage.cpp" line="78"/>
        <source>Wrong password.</source>
        <translation>密碼錯誤。</translation>
    </message>
</context>
<context>
    <name>SetupFlow</name>
    <message>
        <location filename="../src/SetupFlow.cpp" line="29"/>
        <source>Enter the server address.</source>
        <translation>請輸入伺服器位址。</translation>
    </message>
    <message>
        <location filename="../src/SetupFlow.cpp" line="34"/>
        <source>Enter a port from 1 to 65535.</source>
        <translation>請輸入 1 到 65535 之間的連接埠。</translation>
    </message>
    <message>
        <location filename="../src/SetupFlow.cpp" line="62"/>
        <source>The server is reachable, but this terminal&apos;s address (%1) is not registered. Ask the admin to add it on the Devices screen.</source>
        <translation>伺服器可以連線，但本終端的位址（%1）尚未登記。請管理員在「裝置」頁面新增。</translation>
    </message>
    <message>
        <location filename="../src/SetupFlow.cpp" line="74"/>
        <source>The server address could not be saved on this device.</source>
        <translation>伺服器位址無法儲存到本機。</translation>
    </message>
    <message>
        <location filename="../src/SetupFlow.cpp" line="79"/>
        <source>Connected as %1. Server time %2.</source>
        <translation>已連線，本終端為 %1。伺服器時間 %2。</translation>
    </message>
</context>
<context>
    <name>SetupPage</name>
    <message>
        <location filename="../ui/SetupPage.ui" line="13"/>
        <source>Server Setting</source>
        <translation>伺服器設定</translation>
    </message>
    <message>
        <location filename="../ui/SetupPage.ui" line="18"/>
        <source>Enter the address of the web app and test the connection. The server must know this terminal&apos;s IP address: the admin adds it on the Devices screen.</source>
        <translation>輸入網頁端的位址並測試連線。伺服器必須登記了本終端的 IP 位址：由管理員在「裝置」頁面新增。</translation>
    </message>
</context>
</TS>
