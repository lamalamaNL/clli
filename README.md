![Cover](https://storage.lamalama.nl/lamalama/playheart-cover.jpeg)

# CLLI

CLLI development tooling for Lama Lama

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
