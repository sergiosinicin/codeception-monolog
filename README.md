# codeception-monolog

## What is it?

 The default Codeception Logger extension (see http://codeception.com/addons) always uses a `RotatingFileHandler` for Logging, which writes to a `codeception-<date>.log` file. This was not sufficient for me so I created this configurable Monolog logging extension. It allows usage of multiple log handlers with custom configuration (see below for an example).
 Currently only log handlers are supported, that do *not* require objects in their constructor parameters, such as:
 * NativeMailHandler
 * FirePHPHandler
 * RotatingFileHandler
 * HipChatHandler
 * ErrorLogHandler
 * ...
 
The log handlers will currently only be triggered in case of a failed test. 

## Installation

Currently not available via packagist, so you'll have to add this to your `composer.json`:

```
  ...
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:jotweh/codeception-monolog.git"
    }
  ],
  "require": {
    "jotweh/codeception-monolog": "dev-master",
    ...
  }
  ...
```

## Configuration

Enable the Extension in your `codeception.yaml` like this: 

```
extensions:
    enabled:
        - Codeception\Extension\CodeceptionMonolog
```

You can configure multiple log handlers in your `codeception.yaml`:

```
extensions:
    enabled:
        - Codeception\Extension\CodeceptionMonolog
    config:
            Codeception\Extension\CodeceptionMonolog:
                message: "Test %s failed. Please check. Error Message: %s."
                handlers:
                    NativeMailerHandler:
                          to: 'test@example.com,othertest@example.com'
                          subject: 'Email Subject'
                          from: 'Monitoring'
                          level: 400 #error log level
                    HipChatHandler:
                          token: 'somecryptichipchattoken'
                          room: 'HipChatRoomName'
                          name: 'Notifier'
                          notify: true
                          level: 400 #error log level
```

In the `handlers` section, each key is a Monolog handler class name. The values are the constructor parameters for the handler. In the example above, the HipChatHandler and the NativeMailerHandler are used. So when a test fails, an email and a hipchat notification will be sent.







