# SPC Packages

A tool for building and packaging PHP and shared extensions with static-php-cli.

## Requirements

- PHP 8.4 or higher
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

The main command-line tool is `bin/spp`, which uses Symfony Console for command-line parsing and provides several commands:

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

Alternatively, you can specify which packages to build using the `--packages` option:

```
php bin/spp package --packages=pdo  # Build only pdo package
```

<!-- Repository command is not implemented yet -->

### Build and Package

To run both build and package steps in one command:

```
php bin/spp all
```

### Specifying the target architecture

You can specify the target architecture using the `--target` option. This option takes a target triple that Zig understands, such as `x86_64-linux-gnu` or `aarch64-linux-gnu`:

```
php bin/spp build --target=x86_64-linux-gnu # build for x86_64 architecture
# or
php bin/spp build --target=aarch64-linux-gnu # build for aarch64 architecture
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
