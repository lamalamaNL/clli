# Laravel Prompts Improvement Opportunities for CLLI

Based on the [Laravel Prompts documentation](https://laravel.com/docs/12.x/prompts), here are specific opportunities to enhance the CLLI interface.

## Current State

CLLI already uses Laravel Prompts extensively:
- ✅ `text()` - Text input
- ✅ `confirm()` - Yes/no questions
- ✅ `select()` - Option selection
- ✅ `info()` - Informational messages
- ✅ `error()` - Error messages
- ✅ `spin()` - Loading spinners
- ✅ `table()` - Data tables

## Improvement Opportunities

### 1. Add Hints to Prompts

**Current**: Many prompts lack contextual help
**Improvement**: Add `hint` parameter to provide guidance

#### Examples:

**LamaPressNewCommand.php** (line 196-199):
```php
// Current
$this->name = $this->input->getArgument('name') ?? text(
    label: 'What is the name of your application?',
    required: true
);

// Improved
$this->name = $this->input->getArgument('name') ?? text(
    label: 'What is the name of your application?',
    hint: 'This will be used for the directory name and URL (e.g., myapp.test)',
    required: true
);
```

**StagingCreateCommand.php** (line 652):
```php
// Current
$serverId = text(label: 'We need a forge server ID for this command. Please provide a forge server ID', required: true);

// Improved
$serverId = text(
    label: 'We need a forge server ID for this command. Please provide a forge server ID',
    hint: 'You can find this in your Forge dashboard URL or by listing servers',
    required: true
);
```

**LocalConfigUpdateCommand.php** (line 90-94):
```php
// Current
$newValue = text(
    label: "Enter new value for '$selectedKey'",
    default: $currentValue,
    required: 'The configuration value is required.'
);

// Improved
$newValue = text(
    label: "Enter new value for '$selectedKey'",
    default: $currentValue,
    hint: $this->getConfigHint($selectedKey), // Helper method with context
    required: 'The configuration value is required.'
);
```

### 2. Add Placeholders

**Current**: Text inputs don't show example values
**Improvement**: Add `placeholder` parameter

#### Examples:

**StagingCreateCommand.php** (line 698):
```php
// Current
return text(label: 'What is the subdomain we need to deploy to', required: true);

// Improved
return text(
    label: 'What is the subdomain we need to deploy to',
    placeholder: 'E.g. projectname',
    hint: 'This will create: projectname.lamalama.dev',
    required: true
);
```

**StagingPullCommand.php** (line 57-64):
```php
// Already has placeholder - good! ✅
// But could improve validation message
```

### 3. Use Password Prompts for Sensitive Data

**Current**: API keys and tokens use regular `text()` prompts
**Improvement**: Use `password()` for sensitive inputs

#### Examples:

**StagingCreateCommand.php** (line 667):
```php
// Current
$forgeToken = text(label: 'We need a forge token for this command. Please provide a forge token', required: true);

// Improved
use function Laravel\Prompts\password;

$forgeToken = password(
    label: 'We need a forge token for this command. Please provide a forge token',
    hint: 'Get this from https://forge.laravel.com/user/profile#/api',
    required: true
);
```

**StagingCreateCommand.php** (line 387-390):
```php
// Current
$cfToken = text(
    label: 'We need a Cloudflare API token for DNS updates. Please provide your token:',
    required: true
);

// Improved
$cfToken = password(
    label: 'We need a Cloudflare API token for DNS updates. Please provide your token:',
    hint: 'Generate via \'Create Token\' at https://dash.cloudflare.com/profile/api-tokens',
    required: true
);
```

### 4. Use Warning and Alert Messages

**Current**: Uses `error()` for warnings and `info()` for alerts
**Improvement**: Use appropriate message types

#### Examples:

**StagingCreateCommand.php** (line 262):
```php
// Current
error('⚠️  Theme folder not found, run this command from the theme folder');

// Improved
use function Laravel\Prompts\warning;

warning('Theme folder not found. Run this command from the theme folder.');
```

**StagingCreateCommand.php** (line 319-326):
```php
// Current
error('⚠️  Your organization has multiple owners and requires an organization ID.');
error('The organization ID could not be automatically detected.');
info('');
info('Please set your organization ID manually:');
info('  clli config:update forge_organization_id');

// Improved
use function Laravel\Prompts\warning;
use function Laravel\Prompts\alert;

warning('Your organization has multiple owners and requires an organization ID.');
alert('The organization ID could not be automatically detected.');
info('');
info('Please set your organization ID manually:');
info('  clli config:update forge_organization_id');
```

**StagingCreateCommand.php** (line 426):
```php
// Current
info('⚠️ Existing DNS record updated');

// Improved
warning('Existing DNS record updated');
```

### 5. Use Intro and Outro

**Current**: ASCII art in `interact()` method
**Improvement**: Use `intro()` and `outro()` for better presentation

#### Examples:

**All Commands** - Replace ASCII art with:
```php
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

protected function interact(InputInterface $input, OutputInterface $output): void
{
    parent::interact($input, $output);
    
    intro('Lama Lama CLLI');
    // Remove ASCII art
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
    // ... command logic ...
    
    outro('Command completed successfully!');
    
    return Command::SUCCESS;
}
```

### 6. Use Search for Large Lists

**Current**: Uses `select()` for server/organization selection
**Improvement**: Use `search()` for better UX with many options

#### Examples:

**StagingCreateCommand.php** (line 237-241):
```php
// Current
$value = select(
    label: 'Choose a server',
    options: $serverChoices,
    required: true
);

// Improved (if many servers)
use function Laravel\Prompts\search;

$value = search(
    label: 'Choose a server',
    options: fn ($value) => array_filter(
        $serverChoices,
        fn ($name) => stripos($name, $value) !== false
    ),
    placeholder: 'Type to search...',
    required: true
);
```

### 7. Use Suggest for Autocomplete

**Current**: Manual text input for repository names
**Improvement**: Use `suggest()` for autocomplete

#### Examples:

**StagingCreateCommand.php** (line 190-205):
```php
// Current
$repoFromRemote = shell_exec('git config --get remote.origin.url');
// ... manual parsing ...

// Improved
use function Laravel\Prompts\suggest;

$repo = suggest(
    label: 'Repository name',
    options: fn ($value) => $this->getRepositorySuggestions($value),
    placeholder: 'E.g. lamalamaNL/projectname',
    default: $this->getDefaultRepo(),
    required: true
);
```

### 8. Use Forms for Multi-Step Input

**Current**: Multiple sequential prompts
**Improvement**: Use `form()` for related inputs

#### Examples:

**StagingCreateCommand.php** - Initial configuration:
```php
// Current - Multiple separate prompts
$this->subdomain = $this->getSubdomain();
$this->repo = $this->calulateRepo();

// Improved
use function Laravel\Prompts\form;

$config = form()
    ->text('subdomain', 'Subdomain', required: true, placeholder: 'projectname')
    ->text('repo', 'Repository', required: true, placeholder: 'lamalamaNL/projectname')
    ->confirm('createGitRepo', 'Initialize Git repository?', default: true)
    ->submit();

$this->subdomain = $config['subdomain'];
$this->repo = $config['repo'];
$this->createGitRepo = $config['createGitRepo'];
```

### 9. Use Progress Bars for Long Operations

**Current**: Uses `spin()` for all operations
**Improvement**: Use `progress()` for operations with known steps

#### Examples:

**LamaPressNewCommand.php** (line 144-182):
```php
// Current
foreach ($steps as $index => [$method, $message]) {
    spin(
        message: $message,
        callback: fn () => $this->$method(),
    );
}

// Improved
use function Laravel\Prompts\progress;

progress(
    label: 'Setting up LamaPress',
    steps: $steps,
    callback: fn ($step) => $this->{$step[0]}(),
);
```

**StagingCreateCommand.php** (line 144-154):
```php
// Similar improvement opportunity
```

### 10. Use Textarea for Longer Inputs

**Current**: Uses `text()` for potentially long inputs
**Improvement**: Use `textarea()` where appropriate

#### Examples:

**LocalConfigUpdateCommand.php** - For longer config values:
```php
// Current
$newValue = text(
    label: "Enter new value for '$selectedKey'",
    default: $currentValue,
    required: 'The configuration value is required.'
);

// Improved (for certain keys)
use function Laravel\Prompts\textarea;

if ($this->isLongValueKey($selectedKey)) {
    $newValue = textarea(
        label: "Enter new value for '$selectedKey'",
        default: $currentValue,
        required: 'The configuration value is required.',
        hint: 'Press Ctrl+D (or Cmd+D on Mac) when finished'
    );
} else {
    $newValue = text(...);
}
```

### 11. Improve Validation Messages

**Current**: Some validation is basic
**Improvement**: More descriptive validation

#### Examples:

**StagingPullCommand.php** (line 57-64):
```php
// Current
$input->setArgument('connection_info', text(
    label: 'What is the WP Migrate DB connection info?',
    placeholder: 'E.g. https://projectx.lamalama.dev qQSr+EVrJ83uIkME/zQiCBb4V/nVaG1dzh5vmqEq',
    required: 'The WP Migrate DB connection info is required.',
    validate: fn ($value) => preg_match($pattern, $value) !== 0
        ? null
        : null, // Always returns null - validation doesn't work!
));

// Improved
$input->setArgument('connection_info', text(
    label: 'What is the WP Migrate DB connection info?',
    placeholder: 'E.g. https://projectx.lamalama.dev qQSr+EVrJ83uIkME/zQiCBb4V/nVaG1dzh5vmqEq',
    hint: 'Format: <url> <connection-key>',
    required: 'The WP Migrate DB connection info is required.',
    validate: fn ($value) => preg_match($pattern, $value) !== 0
        ? null
        : 'Invalid format. Expected: https://domain.lamalama.dev <connection-key>'
));
```

### 12. Use Pause for Important Information

**Current**: Information is displayed immediately
**Improvement**: Use `pause()` to ensure users read important info

#### Examples:

**LamaPressNewCommand.php** (line 729-736):
```php
// Current
private function displayCredentials(): void
{
    info('');
    info("LamaPress ready on [http://{$this->name}.test]. Build something unexpected.");
    info("Admin ready on [http://{$this->name}.test/wp-admin]. Manage your website here.");
    info("Username: {$this->user}");
    info("Password: {$this->password}");
}

// Improved
use function Laravel\Prompts\pause;

private function displayCredentials(): void
{
    info('');
    info("LamaPress ready on [http://{$this->name}.test]. Build something unexpected.");
    info("Admin ready on [http://{$this->name}.test/wp-admin]. Manage your website here.");
    
    table(
        ['Field', 'Value'],
        [
            ['Username', $this->user],
            ['Password', $this->password],
        ]
    );
    
    pause('Press enter to continue...');
}
```

## Priority Recommendations

### High Priority (Quick Wins)
1. ✅ Add `hint` to all prompts
2. ✅ Add `placeholder` where helpful
3. ✅ Replace `error()` with `warning()` for warnings
4. ✅ Use `password()` for sensitive inputs
5. ✅ Fix validation in `StagingPullCommand.php`

### Medium Priority (Better UX)
6. ✅ Use `intro()` and `outro()` instead of ASCII art
7. ✅ Use `alert()` for important notices
8. ✅ Use `progress()` for multi-step operations
9. ✅ Use `pause()` for credential display

### Low Priority (Nice to Have)
10. ✅ Use `form()` for related inputs
11. ✅ Use `search()` for large option lists
12. ✅ Use `suggest()` for autocomplete
13. ✅ Use `textarea()` for longer inputs

## Implementation Notes

- All Laravel Prompts functions are already available (package installed)
- The `ConfiguresPrompts` trait already handles fallbacks
- Changes are backward compatible
- No breaking changes to existing functionality

## Testing Considerations

When implementing these improvements:
- Test in interactive mode
- Test in non-interactive mode (fallbacks should work)
- Test on macOS, Linux, and Windows (WSL)
- Verify validation still works correctly
- Ensure hints/placeholders don't break existing flows
