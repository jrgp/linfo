# Enabling ncurses (Debian/Ubuntu)

Linfo has a simple ncurses-based UI, relying on php's ncurses extension. This
extension is not bundled in apt on debian/ubuntu, but installing it from source
is easy enough:

#### Install dependencies:

```
sudo apt-get install php5-dev libncurses5-dev
```

#### Compile php extension

```
wget http://pecl.php.net/get/ncurses-1.0.2.tgz
tar xzvf ncurses-1.0.2.tgz
cd ncurses-1.0.2
phpize # generate configure script
./configure
make
sudo make install
```

#### If that succeeded:

```
sudo -i 
echo extension=ncurses.so > /etc/php5/cli/conf.d/ncurses.ini
```

#### Verify:

```
$ php -m | grep ncurses
ncurses
```

####  Run

```
./linfo-curses
```
