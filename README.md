# FilesystemStreamWrapper
Simple PHP Local Filesystem StreamWrapper

# Install

With *Composer*

```bash
composer require brzuchal/filesystem-stream-wrapper
```

# Usage

```php
FilesystemStreamWrapper::register('app', __DIR__ . '/myapp_direcotry')

touch('app://file.txt');
file_put_contents('app://file.txt', "comment\n");
echo file_get_contents('app://file.txt'); // "comment\n"
unlink('app://file.txt');
mkdir('app://directory');
rename('app://directory', 'app://dir');
rmdir('app://dir');

FilesystemStreamWrapper::unregister('app');
```

# Known Issues

PHP doesn't support StreamWrapper in some functions like `chdir()`, `link()`, `symlink()`, `readlink()`, `linkinfo()`, `tempnam()` and `realpath()`. While most of them are rarely used the `realpath()` is widely used.
To deal with that issue the only way is using `Filesystem::realpath()` method or declaring wrapped function in some namespace appropriate for `realpath()` usage which cause PHP internally looking for an function declared in current namespace.

For eg. using `Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand` from [Doctrine2](https://github.com/doctrine/doctrine2/blob/master/lib/Doctrine/ORM/Tools/Console/Command/GenerateProxiesCommand.php#L87) you need to declare function like that:

```php
namespace Doctrine\ORM\Tools\Console\Command;

function realpath() {
    return call_user_func_array("FilesystemStreamWrapper::realpath", func_get_args());
}
```

# License

MIT License

Copyright (c) 2017 Michał Brzuchalski <michal.brzuchalski@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
