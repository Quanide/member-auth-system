<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** 造一張真實可解碼的圖片，而不是 fake 出來的空殼 */
    private function realImage(int $w = 800, int $h = 600): UploadedFile
    {
        $image = imagecreatetruecolor($w, $h);
        imagefill($image, 0, 0, imagecolorallocate($image, 90, 100, 240));

        $path = tempnam(sys_get_temp_dir(), 'avatar').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'avatar.png', 'image/png', null, true);
    }

    #[Test]
    public function 可以上傳頭像(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/me/avatar', ['avatar' => $this->realImage()])
            ->assertOk()
            ->assertJsonPath('data.message', '頭像已更新');

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    #[Test]
    public function 上傳後會被裁切成正方形(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/me/avatar', ['avatar' => $this->realImage(1200, 400)])->assertOk();

        $binary = Storage::disk('public')->get($user->fresh()->avatar_path);
        $size = getimagesizefromstring($binary);

        $this->assertSame($size[0], $size[1], '輸出應為正方形');
        $this->assertSame(512, $size[0]);
    }

    #[Test]
    public function 更換頭像時舊檔案會被刪除(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/me/avatar', ['avatar' => $this->realImage()])->assertOk();
        $first = $user->fresh()->avatar_path;

        $this->actingAs($user)->postJson('/api/me/avatar', ['avatar' => $this->realImage()])->assertOk();
        $second = $user->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    #[Test]
    public function 可以移除頭像(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/me/avatar', ['avatar' => $this->realImage()])->assertOk();
        $path = $user->fresh()->avatar_path;

        $this->actingAs($user)->deleteJson('/api/me/avatar')->assertOk();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function 拒絕偽裝成圖片的檔案(): void
    {
        $user = User::factory()->create();

        // 副檔名是 .png，內容其實是 PHP 腳本
        $path = tempnam(sys_get_temp_dir(), 'evil').'.png';
        file_put_contents($path, '<?php echo "pwned"; ?>');
        $fake = new UploadedFile($path, 'evil.png', 'image/png', null, true);

        $this->actingAs($user)
            ->postJson('/api/me/avatar', ['avatar' => $fake])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->avatar_path);
    }

    #[Test]
    public function 拒絕超過大小上限的檔案(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/me/avatar', ['avatar' => UploadedFile::fake()->create('big.png', 6000, 'image/png')])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['avatar']]);
    }

    #[Test]
    public function 未登入無法上傳頭像(): void
    {
        $this->postJson('/api/me/avatar', ['avatar' => $this->realImage()])->assertStatus(401);
    }
}
