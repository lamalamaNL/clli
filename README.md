![Cover](https://storage.lamalama.nl/lamalama/playheart-cover.jpeg)

# CLLI

CLLI development tooling for Lama Lama

# Commands

## Pull staging
Pull a staging environment to your local machine.
Steps:
- Login to the wordpress staging environment
- Make sure WP migrate is on the lastest version. Update if one is available.
- Copy the connection info under: Tools | WP Migrate | Settings (tab).
- On your local machine open a terminal cd in your code directory.
- Run the following command
```
clli staging:pull
```
- Past the connection info in the terminal.
- Give the CLI some time to finish (it might take sometime. Especially with large repositories/databases/assets).
- Done!

#### Options
If the repo name is different then the subdomain for the test environment, you can overwrite the repository url with the following option.
```
clli staging:pull -r "git@github.com:lamalamaNL/justdiggit2021.git"
// or
clli staging:pull --repository_url="https://github.com/lamalamaNL/justdiggit2021.git"
```

#### Tips
If the CLLI fails the fastest thing to is remove the created directory and run the command again (after identifying and fixing the issue).

#### Todo:
Add some checks before running the complete script.
- Check if the repository url is valid.
- Check if the remote WP migrate is the same version as the local one.



## Local development

Find your local composer.json with the following command, the folder that contains your global composer.json file can be found at `data-dir`

```
composer config --list --global
```

Adjust your local composer.json

```
{
    "require": {
        "lamalama/clli": "dev-main"
    },
    "repositories": [
        {
            "type": "path",
            "url": "/Users/markdevries/Code/clli",
            "options": {
                "symlink": true
            }
        }
    ]
}
```
