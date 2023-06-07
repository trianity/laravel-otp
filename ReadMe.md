# Laravel One Time Password (OTP)

The package was inspired by seshac/otp-generator package.


## 1. Installation

You can install the package via composer:

composer require trianity/laravel-otp
You can publish and run the migrations with:

php artisan vendor:publish --provider="Trianity\Otp\Providers\PackageServiceProvider" --tag="migrations"
php artisan migrate

You can publish the config file with:

php artisan vendor:publish --provider="Trianity\Otp\Providers\PackageServiceProvider" --tag="config"


## 2. Usage

```
use Trianity\Otp\Facades\Otp;

$identifier = Str::random(12);
$otp =  Otp::generate($identifier);

$verify = Otp::validate($identifier, $otp->token);
```
// example response
{
    "status": true
    "message": "OTP is valid"
}

// to get an expiredAt time
```
$expires = Otp::expiredAt($identifier);
```
// example response 
{
    +"status": true
    +"expired_at": Illuminate\Support\Carbon @1611895244^ {
    ....
    #dumpLocale: null
    date: 2021-01-29 04:40:44.0 UTC (+00:00)
    }
}

You have control to update the setting at otp.php config file but you control while generating also

## 3. Advance Usage
```
use Trianity\Otp\Facades\Otp;

$identifier = Str::random(12);
$otp =  Otp::setValidity(30)  // otp validity time in mins
      ->setLength(4)  // Lenght of the generated otp
      ->setMaximumOtpsAllowed(10) // Number of times allowed to regenerate otps
      ->setOnlyDigits(false)  // generated otp contains mixed characters e.g. 'ad2312'
      ->setUseSameToken(true) // if you re-generate OTP, you will get same token
      ->generate($identifier);

$verify = Otp::setAllowedAttempts(10) // number of times they can allow to attempt with wrong token
    ->validate($identifier, $otp->token);
```

## License

The MIT License (MIT). Please see License File for more information.