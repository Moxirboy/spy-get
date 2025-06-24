# Makefile for PHP Composer projects

# Attempt to find PHP executable
PHP_CMD := $(shell command -v php 2> /dev/null)

# Attempt to find a global Composer executable
COMPOSER_EXEC := $(shell command -v composer 2> /dev/null)

# Determine the Composer command to use (initial attempt).
# This COMPOSER_CMD will be used by targets like 'install', 'update', etc.
# .check-composer-ready will verify its usability or help set up composer.phar
ifeq ($(strip $(COMPOSER_EXEC)),) # If global composer is not found
    # Check for local composer.phar only if PHP is also available
    ifneq ($(strip $(PHP_CMD)),)
        ifneq ($(wildcard composer.phar),) # Check if composer.phar exists
            COMPOSER_CMD := $(PHP_CMD) composer.phar
        else
            # No global, no local composer.phar yet, but PHP exists.
            # We might download composer.phar later. For now, COMPOSER_CMD is empty.
            # If get-composer runs, subsequent make invocations or logic would pick up composer.phar
            # The install target will effectively use PHP_CMD + composer.phar if COMPOSER_CMD is empty and composer.phar appears
            COMPOSER_CMD :=
        endif
    else
        # No global composer, and no PHP to run a local composer.phar
        COMPOSER_CMD :=
    endif
else
    COMPOSER_CMD := $(COMPOSER_EXEC) # Use global composer
endif

# Default target when 'make' is run without arguments
.DEFAULT_GOAL := help

# Declare targets that are not actual files
.PHONY: all help get-composer install update validate dump-autoload outdated check-platform clean .check-composer-ready

# 'all' is often an alias for the most common build/install action
all: install

help:
	@echo "PHP Composer Makefile"
	@echo "---------------------"
	@echo "This Makefile helps manage PHP dependencies using Composer."
	@echo ""
	@echo "Available targets:"
	@echo "  make help                  Show this help message."
	@echo "  make get-composer          Download 'composer.phar' locally if it's not found."
	@echo "                             Requires PHP to be installed and in PATH."
	@echo "  make install               Install project dependencies. Relies on .check-composer-ready."
	@echo "  make update                Update project dependencies. Relies on .check-composer-ready."
	@echo "  make validate              Validate 'composer.json'. Relies on .check-composer-ready."
	@echo "  make dump-autoload         Optimize the autoloader. Relies on .check-composer-ready."
	@echo "  make outdated              Show outdated packages. Relies on .check-composer-ready."
	@echo "  make check-platform        Check platform requirements. Relies on .check-composer-ready."
	@echo "  make clean                 Remove 'vendor/', 'composer.lock', and 'composer.phar'."
	@echo ""
	@echo "Usage: make [target]"
	@echo ""
	@echo "Current setup (initial detection by Makefile parsing):"
ifeq ($(strip $(PHP_CMD)),)
	@echo "  PHP: Not found in PATH. 'get-composer' and Composer operations will likely fail."
else
	@echo "  PHP: $(PHP_CMD)"
endif
# Prerequisite target to ensure PHP and a way to run Composer are available.
.check-composer-ready:
	@echo "--- Running .check-composer-ready ---"
	@# 1. Ensure PHP is available, as it's fundamental.
	@if [ -z "$(PHP_CMD)" ]; then \
		echo "Error: 'php' command not found in PATH." >&2; \
		echo "PHP is required to run Composer or download 'composer.phar'." >&2; \
		echo "Please install PHP and ensure it is in your PATH." >&2; \
		exit 1; \
	else \
		echo "PHP found: $(PHP_CMD)"; \
	fi

	@# 2. Determine the effective composer command for subsequent operations.
	@#    This uses a shell variable _effective_composer_cmd for logic within this recipe.
	@_effective_composer_cmd=""; \
	if [ -n "$(COMPOSER_EXEC)" ]; then \
		_effective_composer_cmd="$(COMPOSER_EXEC)"; \
		echo "Using global Composer: $$_effective_composer_cmd"; \
	elif [ -f composer.phar ]; then \
		_effective_composer_cmd="$(PHP_CMD) composer.phar"; \
		echo "Using local composer.phar: $$_effective_composer_cmd"; \
	else \
		echo "Global Composer not found and 'composer.phar' not found locally."; \
		echo "Attempting to download 'composer.phar' via 'make get-composer'..."; \
		if $(MAKE) get-composer; then \
			if [ -f composer.phar ]; then \
				_effective_composer_cmd="$(PHP_CMD) composer.phar"; \
				echo "'composer.phar' is now available. Will use: $$_effective_composer_cmd"; \
			else \
				echo "Error: 'make get-composer' ran but 'composer.phar' is still not found." >&2; \
				exit 1; \
			fi; \
		else \
			echo "Error: 'make get-composer' failed." >&2; \
			exit 1; \
		fi; \
	fi

	@# 3. Final sanity check using the determined shell variable _effective_composer_cmd.
	@if [ -z "$$_effective_composer_cmd" ]; then \
		echo "Error: Could not determine a valid Composer command." >&2; \
		exit 1; \
	fi
	@# If _effective_composer_cmd uses composer.phar, PHP must be present (checked in step 1).
	@echo "Effective composer command for operations: $$_effective_composer_cmd"
	@echo "--- .check-composer-ready finished ---"


# Target to download composer.phar
get-composer:
ifndef PHP_CMD
	@echo "Error: 'php' command not found in PATH. Cannot download 'composer.phar'." >&2
	@exit 1
endif
	@if [ -f composer.phar ]; then \
		echo "'composer.phar' already exists locally (called by get-composer)."; \
	else \
		echo "Downloading 'composer.phar' (called by get-composer)..."; \
		if ! $(PHP_CMD) -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"; then \
			echo "Error: Failed to download composer-setup.php using PHP copy." >&2; exit 1; \
		fi; \
		if [ ! -f composer-setup.php ]; then \
			echo "Error: composer-setup.php was not downloaded." >&2; exit 1; \
		fi; \
		if ! $(PHP_CMD) composer-setup.php --quiet; then \
			echo "Error: composer-setup.php failed during execution." >&2; rm -f composer-setup.php; exit 1; \
		fi; \
		$(PHP_CMD) -r "if (file_exists('composer-setup.php')) unlink('composer-setup.php');"; \
		if [ ! -f composer.phar ]; then \
			echo "Error: 'composer.phar' was not created by the installer." >&2; \
			echo "Please check for errors from 'composer-setup.php' or PHP configuration." >&2; \
			exit 1; \
		fi; \
		echo "'composer.phar' downloaded successfully (called by get-composer)."; \
	fi

# --- Composer Targets ---
# These targets depend on .check-composer-ready.
# They will use the COMPOSER_CMD determined by Makefile's top-level logic.
# If COMPOSER_CMD was empty and get-composer (via .check-composer-ready) created composer.phar,
# then PHP_CMD + composer.phar will be used.

define COMPOSER_DO
	@if [ -n "$(COMPOSER_CMD)" ]; then \
		echo "Executing: $(COMPOSER_CMD) $(1)"; \
		$(COMPOSER_CMD) $(1); \
	elif [ -n "$(PHP_CMD)" ] && [ -f composer.phar ]; then \
		echo "Executing: $(PHP_CMD) composer.phar $(1)"; \
		$(PHP_CMD) composer.phar $(1); \
	else \
		echo "Error: Composer command not available. PHP_CMD='$(PHP_CMD)', COMPOSER_CMD='$(COMPOSER_CMD)', composer.phar exists? $(wildcard composer.phar)" >&2; \
		exit 1; \
	fi
endef

install: .check-composer-ready
	@echo "Installing project dependencies..."
	$(call COMPOSER_DO, install --prefer-dist --no-progress --no-interaction --optimize-autoloader)

update: .check-composer-ready
	@echo "Updating project dependencies..."
	$(call COMPOSER_DO, update --no-progress --no-interaction)

validate: .check-composer-ready
	@echo "Validating 'composer.json'..."
	$(call COMPOSER_DO, validate)

dump-autoload: .check-composer-ready
	@echo "Dumping optimized autoloader..."
	$(call COMPOSER_DO, dump-autoload --optimize)

outdated: .check-composer-ready
	@echo "Checking for outdated packages..."
	$(call COMPOSER_DO, outdated)

check-platform: .check-composer-ready
	@echo "Checking platform requirements..."
	$(call COMPOSER_DO, check-platform-reqs)

clean:
	@echo "Cleaning project..."
	@if [ -d "vendor" ]; then rm -rf vendor; echo "Removed 'vendor/' directory."; else echo "'vendor/' directory not found."; fi
	@if [ -f "composer.lock" ]; then rm -f composer.lock; echo "Removed 'composer.lock' file."; else echo "'composer.lock' file not found."; fi
	@if [ -f "composer.phar" ]; then rm -f composer.phar; echo "Removed 'composer.phar' file."; fi
	@echo "Clean complete."
