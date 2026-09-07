/**
 * 上傳前在瀏覽器端壓縮頭像。
 *
 * 好處有三：省使用者流量、避開後端的尺寸上限、上傳更快。
 * 注意這只是優化手段——真正的型別與尺寸校驗仍在後端做，
 * 繞過前端直接打 API 一樣會被擋下。
 */
export async function compressImage(file: File, maxSize = 512): Promise<File> {
  const bitmap = await createImageBitmap(file).catch(() => null)

  // 瀏覽器解不開就原樣送出，交給後端判斷
  if (!bitmap) return file

  try {
    const side = Math.min(bitmap.width, bitmap.height)
    const target = Math.min(side, maxSize)

    const canvas = document.createElement('canvas')
    canvas.width = target
    canvas.height = target

    const ctx = canvas.getContext('2d')
    if (!ctx) return file

    // 居中裁切成正方形，與後端的裁切邏輯保持一致
    ctx.drawImage(
      bitmap,
      (bitmap.width - side) / 2,
      (bitmap.height - side) / 2,
      side,
      side,
      0,
      0,
      target,
      target,
    )

    const blob = await new Promise<Blob | null>((resolve) =>
      canvas.toBlob(resolve, 'image/webp', 0.86),
    )

    if (!blob) return file

    return new File([blob], 'avatar.webp', { type: 'image/webp' })
  } finally {
    bitmap.close()
  }
}
