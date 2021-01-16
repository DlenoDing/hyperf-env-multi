# hyperf-multi-env
hyperf 框架多 env 文件支持
就只是改了https://github.com/qbhy/hyperf-multi-env
兼容hyperf2.1（因为自己急需使用，那边的提交了PR，不知道什么时候才能合并，只能自己打包了）

## 安装 - install
```bash
$ composer require dleno/hyperf-env-multi
```

## 使用 - usage
只需要启动的时候设置 APP_ENV 配置，扩展包就会自动根据 env 来查找 env 文件配置。

比如 APP_ENV 为 `testing` 那么会加载 `.env.testing` 文件配置。
> `.env.testing` 没有的配置，还是会使用 `.env` 文件的配置来加载。所以建议 .env 放共有的配置。

## 示例 - examples
* 通过 `.env` 配置
    ```dotenv
    APP_ENV=local
    ```
* 命令行直接启动
    ```bash
    $ export APP_ENV=production && php bin/hyperf.php start
    ```
    > 有环境变量污染的可能性，请小心使用
* docker 启动
    ```bash
    $ docker run --env APP_ENV=production -d -p 9501:9501 your-image 
    ```
* phpunit 
    ```xml
    <phpunit>
    <!--其他配置-->
        <php>
            <env name="APP_ENV" value="testing"/>
        </php>
    </phpunit>
    ```
