# SPC Packages

A tool for building and packaging PHP and shared extensions with static-php-cli.

## Requirements

- PHP 8.3 or higher
- Docker (unless you pass `--command="spc"` to `bin/spp build`)
- ruby
- fpm (gem)
- rpmbuild (for creating RPM package repository)
- dpkg-deb (for creating DEB package repository)

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/static-php/spc-packages.git
   cd spc-packages
   ```

2. Install dependencies:
   ```
   composer install
   ```

## Configuration

The build process is configured using the `config/craft.yml` file.

## Usage

The main command-line tool is `bin/spp`, which provides several commands:

### Build PHP

To build PHP with the configured extensions:

```
php bin/spp build
```

This will:
1. Copy the configuration to the static-php-cli vendor directory
2. Run static-php-cli to build PHP with the specified extensions
3. Copy the built files to the `build` directory

### Create Packages

To create RPM and DEB packages for the built PHP binaries and extensions:

```
php bin/spp package
```

You can specify which package types to build (RPM, DEB, or both) using the `--type` parameter:

```
php bin/spp package --type=rpm     # Build only RPM packages
php bin/spp package --type=deb     # Build only DEB packages
php bin/spp package --type=rpm,deb # Build both RPM and DEB packages (default)
```

This will:
1. Create packages for each SAPI (cli, fpm, embed)
2. Create packages for each extension
3. Store the packages in the `dist/rpm` and/or `dist/deb` directories, depending on the package types specified

Alternatively, you can specify which packages to build using the `--package` parameter:

```
php bin/spp package --package=pdo  # Build only pdo package
```

### Build repository

To create a package repository from the built packages:

```
php bin/spp repo
```

### Build, Package and Repo

To run all steps in one command:

```
php bin/spp all
```

### Using system compilation tools or musl

The build defaults to using bin/spc-gnu-docker to build against glibc 2.17. If you want to use your system's compilation tools or build against musl, you can pass the `--command` option:

```
php bin/spp build --command=spc # build on your local system
# or
php bin/spp build --command="spc-alpine-docker" # build against musl, not supported yet
```

## Output

The build process produces:

- PHP binaries in `build/bin/`
- PHP modules in `build/modules/`
- PHP libraries in `build/lib/`
- RPM packages in `dist/rpm/`
- DEB packages in `dist/deb/`

## Links

- [static-php-cli](https://github.com/crazywhalecc/static-php-cli)
- [Static PHP Website](https://static-php.dev)
