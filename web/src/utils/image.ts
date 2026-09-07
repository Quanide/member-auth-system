/**
 * 上传前在浏览器端压缩头像。
 *
 * 好处有三：省用户流量、避开后端的尺寸上限、上传更快。
 * 注意这只是优化手段——真正的类型与尺寸校验仍在后端做，
 * 绕过前端直接打 API 一样会被挡下。
 */
export async function compressImage(file: File, maxSize = 512): Promise<File> {
  const bitmap = await createImageBitmap(file).catch(() => null)

  // 浏览器解不开就原样送出，交给后端判断
  if (!bitmap) return file

  try {
    const side = Math.min(bitmap.width, bitmap.height)
    const target = Math.min(side, maxSize)

    const canvas = document.createElement('canvas')
    canvas.width = target
    canvas.height = target

    const ctx = canvas.getContext('2d')
    if (!ctx) return file

    // 居中裁切成正方形，与后端的裁切逻辑保持一致
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
