关于Promise 与EventLoop
============

##安装

###依赖扩展

* php-libevent
    
  在php.net中的不支持php7,需要自行下载非官方包安装,[从github下载](https://github.com/expressif/pecl-event-libevent)
  mac下开发过程中,如果遇到
  
        [warn] kq_init: detected broken kqueue; not using.: Undefined error: 0
  错误时,需要升级libevent至2.1.7
      