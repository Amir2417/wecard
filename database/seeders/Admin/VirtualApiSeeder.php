<?php

namespace Database\Seeders\Admin;

use App\Models\VirtualCardApi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VirtualApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
          $virtual_card_apis = array(
            array('id' => '1','admin_id' => '1','card_details' => '<p>This card is property of StripCard, Wonderland. Misuse is criminal offense. If found, please return to StripCard or to the nearest bank.</p>','config' => '{"flutterwave_secret_key":"FLWSECK_TEST-SANDBOXDEMOKEY-X","flutterwave_secret_hash":"AYxcfvgbhnj@34","flutterwave_url":"https:\/\/api.flutterwave.com\/v3","sudo_api_key":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJfaWQiOiI2NGI2NWExZmZjM2I2NDM5ZjdkNTZjYzIiLCJlbWFpbEFkZHJlc3MiOiJ1c2VyQGFwcGRldnMubmV0IiwianRpIjoiNjRiNjYyNjdmYzNiNjQzOWY3ZDViZjI2IiwibWVtYmVyc2hpcCI6eyJfaWQiOiI2NGI2NWExZmZjM2I2NDM5ZjdkNTZjYzUiLCJidXNpbmVzcyI6eyJfaWQiOiI2NGI2NWExZmZjM2I2NDM5ZjdkNTZjYzAiLCJuYW1lIjoiQXBwZGV2c1giLCJpc0FwcHJvdmVkIjpmYWxzZX0sInVzZXIiOiI2NGI2NWExZmZjM2I2NDM5ZjdkNTZjYzIiLCJyb2xlIjoiQVBJS2V5In0sImlhdCI6MTY4OTY3NDM0MywiZXhwIjoxNzIxMjMxOTQzfQ.MTKO352CEfxG4SUhpfAWu3mkHilLL8Y-oufD6WWCiH4","sudo_vault_id":"tntbuyt0v9u","sudo_url":"https:\/\/api.sandbox.sudo.cards","sudo_mode":"sandbox","stripe_public_key":"pk_test_51NjGM4K6kUt0AggqD10PfWJcB8NxJmDhDptSqXPpX2d4Xcj7KtXxIrw1zRgK4jI5SIm9ZB7JIhmeYjcTkF7eL8pc00TgiPUGg5","stripe_secret_key":"sk_test_51NjGM4K6kUt0Aggqfejd1Xiixa6HEjQXJNljEwt9QQPOTWoyylaIAhccSBGxWBnvDGw0fptTvGWXJ5kBO7tdpLNG00v5cWHt96","stripe_url":"https:\/\/api.stripe.com\/v1","name":"sudo"}','created_at' => '2023-08-29 15:06:28','updated_at' => '2023-09-19 20:29:58','image' => 'seeder/virtual-card.webp')
          );

        VirtualCardApi::insert($virtual_card_apis);
    }
}
