<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
  /**
   * The current password being used by the factory.
   */
  protected static ?string $password;

  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    // 日本人の名前配列
    $japaneseNames = [
      '田中太郎',
      '佐藤花子',
      '鈴木一郎',
      '高橋美咲',
      '渡辺健太',
      '伊藤さくら',
      '山田大輔',
      '中村麻衣',
      '小林隆志',
      '加藤愛',
      '吉田翔太',
      '山本あかり',
      '佐々木誠',
      '松本真由美',
      '井上拓也',
      '木村優子',
      '林直樹',
      '清水千尋',
      '山崎智彦',
      '森下えり',
      '池田雄一',
      '橋本さやか',
      '石川洋平',
      '前田奈津子',
      '岡田晃',
      '長谷川みどり',
      '藤田康介',
      '後藤理恵',
      '近藤雅人',
      '酒井舞',
      '竹内隼人',
      '金子綾',
      '村上和也',
      '斎藤麗',
      '遠藤信也',
      '青木美穂',
      '坂本翼',
      '西村香織',
      '福田拓海',
      '太田彩香'
    ];

    return [
      'name' => $japaneseNames[array_rand($japaneseNames)],
      'email' => fake()->unique()->safeEmail(),
      'email_verified_at' => now(),
      'password' => static::$password ??= Hash::make('password'),
      'remember_token' => Str::random(10),
    ];
  }

  /**
   * Indicate that the model's email address should be unverified.
   */
  public function unverified(): static
  {
    return $this->state(fn(array $attributes) => [
      'email_verified_at' => null,
    ]);
  }
}
