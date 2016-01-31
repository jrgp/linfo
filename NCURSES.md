# Enabling ncurses

Linfo has a simple ncurses-based UI, relying on php's ncurses extension.

## Fedora

One must only use the duke nukem forever package manager:

```bash
sudo dnf install php-pecl-ncurses
```

## Ubuntu/debian

This extension is not bundled in apt on debian/ubuntu, but installing it from source is easy enough:

#### Install dependencies:

```bash
sudo apt-get install php5-dev libncurses5-dev
```

#### Compile php extension

```bash
wget http://pecl.php.net/get/ncurses-1.0.2.tgz
tar xzvf ncurses-1.0.2.tgz
cd ncurses-1.0.2
phpize # generate configure script
./configure
make
sudo make install
```

#### If that succeeded:

```bash
sudo -i 
echo extension=ncurses.so > /etc/php5/cli/conf.d/ncurses.ini
```

#### Verify:

```bash
$ php -m | grep ncurses
ncurses
```

####  Run

```bash
./linfo-curses
```
