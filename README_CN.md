# Nervsys

README: [English](README.md) | [简体中文](README_CN.md)

![release](https://img.shields.io/badge/release-8.0.0-blue?style=flat-square)
![issues](https://img.shields.io/github/issues/Jerry-Shaw/NervSys?style=flat-square)
![contributors](https://img.shields.io/github/contributors/Jerry-Shaw/NervSys?style=flat-square)
![last-commit](https://img.shields.io/github/last-commit/Jerry-Shaw/NervSys?style=flat-square)
![license](https://img.shields.io/github/license/Jerry-Shaw/NervSys?style=flat-square)
[![QQ](https://img.shields.io/badge/QQ交流群-191879883-blue?style=flat-square)](https://qm.qq.com/cgi-bin/qm/qr?k=FJimjw1l5qKXGdDVSmyoq2-PTQ2ZTqBy&jump_from=github)  

## About Nervsys

* 什么是"Nervsys"?  
一个非常轻便的PHP开发框架，使用和集成都相当方便。  

* 为什么要取名为"Nervsys"?  
最开始，我们希望他能像神经细胞一样工作，相互结合起来，各自分工，可以形成以数据为导向的处理系统，并不需要依赖具体的处理命令。

* 有小名么?  
**NS**, 我们大部分人都这么叫他，但是，不要跟任天堂的NS游戏机搞混了.  

* 系统需求:  
PHP **7.4+** 及以上。任意的Web服务器环境或者在命令行下运行他。  

* 用途举例:  
    1. 普通的网站后端开发框架
    2. 各类App的后端接口控制器框架
    3. 程序通信控制端
    4. 其他...

## Installation

1. 克隆或者下载源码，保存到目标机器的任意位置。每台机器只需要一份副本即可，哪怕是有多个项目一起在运行。
2. 在项目入口脚本中引用"NS.php"，然后通过"NS::new();"来启动系统。
3. 如果需要，在"NS::new();"启动系统前，使用"/Ext/libCoreApi"来注册自己的模块和方法。
4. 如果一切没有改动，在项目下"/api"目录里面写项目api类，在"/app"目录下，写业务类即可。

## Usage

##### Notice: All demo code is under default system settings.












## Todo
- [x] 基础核心和扩展逻辑
- [x] 应用代码运行环境监测逻辑
- [x] 第三方路由模块支持
- [x] 第三方错误处理模块支持
- [x] 第三方数据读取/输出模块支持
- [x] 基于路径的钩子函数注册功能支持
- [ ] Socket 相关功能
- [ ] ML/AI 相关内置路由
- [ ] 更多详细的文档和范例

## Supporters

感谢 [JetBrains](https://www.jetbrains.com/?from=Nervsys) 提供的开源许可证对本项目的支持。  

## License

本软件使用 Apache License 2.0 协议，请严格遵照协议内容发行和传播。
您能在"LICENSE.md"中找到该协议内容的副本。