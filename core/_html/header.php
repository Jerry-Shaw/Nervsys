<?php
load_lib('core', 'ctrl_language');
$lang_common = ctrl_language::get_common();
?>
<header id="header" class="clear">
    <div class="left clear">
        <div class="logo">
            <a href="/">
                <img class="img_logo" src="/core/_image/logo.png">
            </a>
        </div>
        <nav id="nav">
            <ul class="clear">
                <li>
                    <a href="/"><?= $lang_common['nav_home']; ?></a>
                </li>
                <li>
                    <a href="/project/"><?= $lang_common['nav_project']; ?></a>
                </li>
                <li>
                    <a href="/petition/"><?= $lang_common['nav_petition']; ?></a>
                </li>
            </ul>
        </nav>
    </div>
    <div class="right clear">
        <div class="btn_lang">
            <ul id="lang_section" class="clear">
                <li class="section">
                    <a data="zh-CN" <?= 'zh-CN' === ctrl_language::$lang ? 'class="curr_lang"' : ''; ?>>中</a>
                </li>
                <li class="section">
                    <a data="en-US" <?= 'en-US' === ctrl_language::$lang ? 'class="curr_lang"' : ''; ?>>EN</a>
                </li>
            </ul>
        </div>
        <div class="btn_action">
            <ul>
                <li>
                    <a id="sign_in" class="sign_in" href="/user/"><?= $lang_common['nav_login']; ?></a>
                </li>
                <li>
                    <a id="sign_up" class="sign_up" href="/user/join.php"><?= $lang_common['nav_join']; ?></a>
                </li>
            </ul>
        </div>
        <div class="avatar">
            <a id="user_home">
                <img id="user_head" class="img_avatar" src="/core/_image/header/img_mascot.png">
            </a>
        </div>
        <div class="search">
            <form action="/search/" method="get">
                <input type="text" class="search_text" placeholder="<?= $lang_common['nav_seek']; ?>">
                <input type="image" class="search_icon" src="/core/_image/header/icon_search.png">
            </form>
        </div>
    </div>
    <div class="nav_bottom">
        <ul class="clear">
            <li>
                <a href="/">
                    <div>
                        <img src="/core/_image/header/icon_home.png">
                    </div>
                    <?= $lang_common['nav_home']; ?>
                </a>
            </li>
            <li>
                <a href="/project/">
                    <div>
                        <img src="/core/_image/header/icon_project.png">
                    </div>
                    <?= $lang_common['nav_project']; ?>
                </a>
            </li>
            <li>
                <a href="/petition/">
                    <div>
                        <img src="/core/_image/header/icon_petition.png">
                    </div>
                    <?= $lang_common['nav_petition']; ?>
                </a>
            </li>
            <li>
                <a href="/user">
                    <div>
                        <img src="/core/_image/header/icon_panel.png">
                    </div>
                    <?= $lang_common['nav_panel']; ?>
                </a>
            </li>
        </ul>
    </div>
</header>