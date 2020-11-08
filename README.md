# Nervsys

README: [English](README.md) | [简体中文](README_CN.md)

![release](https://img.shields.io/badge/release-8.0.0-blue?style=flat-square)
![issues](https://img.shields.io/github/issues/Jerry-Shaw/NervSys?style=flat-square)
![contributors](https://img.shields.io/github/contributors/Jerry-Shaw/NervSys?style=flat-square)
![last-commit](https://img.shields.io/github/last-commit/Jerry-Shaw/NervSys?style=flat-square)
![license](https://img.shields.io/github/license/Jerry-Shaw/NervSys?style=flat-square)  

## About Nervsys

* What is "Nervsys"?  
A very slight PHP framework, very easy to use and integrate.  

* Why called "Nervsys"?  
At the very beginning, as we hoped. The unit could process more like a nerve cell, and build together as a pure data-based calling system. Exact commands would be unnecessary to tell the system what to do.  

* Any short name?  
**NS**, that's what most of us call it, but, don't mix it up with Nintendo Switch.  

* Requirements:  
PHP **7.4+** and above. Any kind of web server or running under CLI mode.  

* Usage example:  
    1. Ordinary framework for Web-backend-developing
    2. API controller for all types of Apps
    3. Client for program communication
    4. More...

## Installation

1. Clone or download source code to anywhere on your machine. Only one copy is required on the same machine even multiple projects exist.
2. Include "NS.php" in the main entry script of the project, and call it with using "NS::new();".
3. If needed, using "/Ext/libCoreApi" to register your own modules and functions before calling "NS::new();".
4. Write your API code classes under "/api", application code classes under "/app", if not changed, and there you go.

## Usage

##### Notice: All demo code is under default system settings.












## Todo
- [x] Basic Core and Ext logic
- [x] App code env detection logic
- [x] Custom router module support
- [x] Custom error handler module support
- [x] Custom data reader/output module support
- [x] Path based hook registration function support
- [ ] Socket related functions
- [ ] ML/AI based internal router
- [ ] More detailed documents and demos

## Supporters

Thanks to [JetBrains](https://www.jetbrains.com/?from=Nervsys) for supporting the project, within the Open Source Support Program.  

## License

This software is licensed under the terms of the Apache 2.0 License.  
You can find a copy of the license in the LICENSE.md file.