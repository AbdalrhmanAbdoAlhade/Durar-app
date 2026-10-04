<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Traits\ApiResponseTrait;
use App\Traits\ImageConverterTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\ProductService;

class ProductController extends Controller
{
    use ApiResponseTrait, ImageConverterTrait;

    public function __construct(
        protected ProductService $productService
    ) {}

    // GET /api/products (public)
    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->listActive($request, 10);

        return response()->json([
            'success' => true,
            'data'    => ProductResource::collection($products)->response()->getData(true)['data'],
            'meta'    => [
                'current_page' => $products->currentPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
                'last_page'    => $products->lastPage(),
            ],
            'links'   => [
                'first' => $products->url(1),
                'last'  => $products->url($products->lastPage()),
                'prev'  => $products->previousPageUrl(),
                'next'  => $products->nextPageUrl(),
            ],
        ]);
    }

    // GET /api/products/{product} (public)
  public function show(Product $product): JsonResponse
{
    // ===== QR Code (محفوظ في الداتابيز) =====
    if (empty($product->qr_code)) {
        $qrPath = $this->generateAndSaveProductQrCode($product->id);
        $product->update(['qr_code' => $qrPath]);
        $product->qr_code = $qrPath;
    }

    return $this->success(
        new ProductResource(
            $product->load(['category', 'images'])
        )
    );
}

    // GET /api/admin/products (admin)
    public function adminIndex(Request $request): JsonResponse
    {
        $products = Product::query()
            ->when(
                $request->filled('category_id'),
                fn ($q) => $q->where(
                    'category_id',
                    $request->integer('category_id')
                )
            )
            // ===== فلترة الحقول الجديدة =====
            ->when(
                $request->filled('classification'),
                fn ($q) => $q->where('classification', 'like', '%' . $request->string('classification') . '%')
            )
            ->when(
                $request->filled('hardness'),
                fn ($q) => $q->where('hardness', 'like', '%' . $request->string('hardness') . '%')
            )
            ->when(
                $request->filled('origin_details'),
                fn ($q) => $q->where('origin_details', 'like', '%' . $request->string('origin_details') . '%')
            )
            ->when(
                $request->filled('origin_country'),
                fn ($q) => $q->where('origin_country', $request->string('origin_country'))
            )
            ->when(
                $request->filled('weight_unit'),
                fn ($q) => $q->where('weight_unit', $request->string('weight_unit'))
            )
            ->with(['category', 'images'])
            ->latest()
            ->paginate(20);

        return $this->success(
            ProductResource::collection($products)
        );
    }

    // POST /api/admin/products (admin)
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => [
                'required',
                'exists:categories,id',
            ],
            'name_ar' => [
                'required',
                'string',
                'max:255',
            ],
            'name_en' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:products,slug',
            ],
            'description_ar' => [
                'nullable',
                'string',
            ],
            'description_en' => [
                'nullable',
                'string',
            ],
            'cover_image' => [
                'nullable',
                'image',
                'max:4096',
            ],
            'gallery' => [
                'nullable',
                'array',
            ],
            'gallery.*' => [
                'image',
                'max:4096',
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'discount_percentage' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'quantity' => [
                'required',
                'integer',
                'min:0',
            ],
            'is_rare' => [
                'nullable',
                'boolean',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],

            // ===== الحقول الجديدة =====
            'classification' => [
                'nullable',
                'string',
                'max:255',
            ],
            'hardness' => [
                'nullable',
                'string',
                'max:50',
            ],
            'origin_country' => [
                'nullable',
                'string',
                'size:2',
            ],
            'origin_details' => [
                'nullable',
                'string',
                'max:255',
            ],
            'weight' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'weight_unit' => [
                'nullable',
                'string',
                'max:20',
            ],
        ]);

        // gallery ليست column في products
        unset($data['gallery']);

        // Generate slug automatically if not provided
        $data['slug'] = $data['slug']
            ?? $this->uniqueSlug($data['name_en']);

        /*
        |--------------------------------------------------------------------------
        | Cover Image
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->storeImageAsWebp(
                $request->file('cover_image'),
                'products'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create Product
        |--------------------------------------------------------------------------
        */
        $product = Product::create($data);
          // ===== توليد وحفظ الـ QR بعد إنشاء المنتج =====
          $qrPath = $this->generateAndSaveProductQrCode($product->id);
          $product->update(['qr_code' => $qrPath]);
          $product->qr_code = $qrPath;

        /*
        |--------------------------------------------------------------------------
        | Gallery Images
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $i => $file) {
                $product->images()->create([
                    'image' => $this->storeImageAsWebp(
                        $file,
                        'products'
                    ),
                    'sort_order' => $i,
                ]);
            }
        }

        return $this->success(
            new ProductResource(
                $product->load(['category', 'images'])
            ),
            'Product created',
            201
        );
    }

    // PUT /api/admin/products/{product} (admin)
    public function update(
        Request $request,
        Product $product
    ): JsonResponse {
        $data = $request->validate([
            'category_id' => [
                'sometimes',
                'required',
                'exists:categories,id',
            ],
            'name_ar' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'name_en' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:products,slug,' . $product->id,
            ],
            'description_ar' => [
                'nullable',
                'string',
            ],
            'description_en' => [
                'nullable',
                'string',
            ],
            'cover_image' => [
                'nullable',
                'image',
                'max:4096',
            ],
            'gallery' => [
                'nullable',
                'array',
            ],
            'gallery.*' => [
                'image',
                'max:4096',
            ],
            'price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],
            'discount_percentage' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'quantity' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
            'is_rare' => [
                'nullable',
                'boolean',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],

            // ===== الحقول الجديدة =====
            'classification' => [
                'nullable',
                'string',
                'max:255',
            ],
            'hardness' => [
                'nullable',
                'string',
                'max:50',
            ],
            'origin_country' => [
                'nullable',
                'string',
                'size:2',
            ],
            'origin_details' => [
                'nullable',
                'string',
                'max:255',
            ],
            'weight' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'weight_unit' => [
                'nullable',
                'string',
                'max:20',
            ],
        ]);

        // gallery ليست column في products
        unset($data['gallery']);

        /*
        |--------------------------------------------------------------------------
        | Generate New slug
        |--------------------------------------------------------------------------
        */
        if (
            isset($data['name_en']) &&
            $data['name_en'] !== $product->name_en &&
            empty($data['slug'])
        ) {
            $data['slug'] = $this->uniqueSlug(
                $data['name_en'],
                $product->id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Cover Image
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('cover_image')) {
            // Delete old image
            if (!empty($product->cover_image)) {
                Storage::disk('public')->delete(
                    ltrim($product->cover_image, '/')
                );
            }

            // Store new WebP image
            $data['cover_image'] = $this->storeImageAsWebp(
                $request->file('cover_image'),
                'products'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Product
        |--------------------------------------------------------------------------
        */
        $product->update($data);

          // ===== إعادة توليد الـ QR =====
            $qrPath = $this->generateAndSaveProductQrCode($product->id);
            $product->update(['qr_code' => $qrPath]);
            $product->qr_code = $qrPath;
        /*
        |--------------------------------------------------------------------------
        | Add Gallery Images
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('gallery')) {
            $nextOrder = (int) $product->images()->max('sort_order') + 1;

            foreach ($request->file('gallery') as $i => $file) {
                $product->images()->create([
                    'image' => $this->storeImageAsWebp(
                        $file,
                        'products'
                    ),
                    'sort_order' => $nextOrder + $i,
                ]);
            }
        }

        return $this->success(
            new ProductResource(
                $product->fresh(['category', 'images'])
            ),
            'Product updated'
        );
    }

    // DELETE /api/admin/products/{product} (admin)
    public function destroy(Product $product): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Delete Cover Image
        |--------------------------------------------------------------------------
        */
        if (!empty($product->cover_image)) {
            Storage::disk('public')->delete(
                ltrim($product->cover_image, '/')
            );
        }
        // حذف ملف الـ QR
        if (!empty($product->qr_code)) {
            Storage::disk('public')->delete(
                str_replace('storage/', '', $product->qr_code)
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Delete Gallery Images
        |--------------------------------------------------------------------------
        */
        foreach ($product->images as $image) {
            if (!empty($image->image)) {
                Storage::disk('public')->delete(
                    ltrim($image->image, '/')
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Product
        |--------------------------------------------------------------------------
        */
        $product->delete();

        return $this->success(
            null,
            'Product deleted'
        );
    }

    // DELETE /api/admin/products/{product}/images/{image} (admin)
    public function destroyImage(
        Product $product,
        ProductImage $image
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Make sure image belongs to product
        |--------------------------------------------------------------------------
        */
        if ($image->product_id !== $product->id) {
            return $this->error(
                'Not found',
                404
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Image File
        |--------------------------------------------------------------------------
        */
        if (!empty($image->image)) {
            Storage::disk('public')->delete(
                ltrim($image->image, '/')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Database Record
        |--------------------------------------------------------------------------
        */
        $image->delete();

        return $this->success(
            null,
            'Product image deleted'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unique Slug
    |--------------------------------------------------------------------------
    */
    protected function uniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Product::where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($q) => $q->where(
                        'id',
                        '!=',
                        $ignoreId
                    )
                )
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    // PATCH /api/admin/products/{product}/rare (admin)
    public function toggleRare(Product $product): JsonResponse
    {
        $product->update([
            'is_rare' => ! $product->is_rare,
        ]);

        return $this->success(
            new ProductResource(
                $product->fresh(['category', 'images'])
            ),
            $product->is_rare ? 'Marked as rare' : 'Removed from rare'
        );
    }
  /**
 * توليد وحفظ QR Code للمنتج
 * يرجع المسار النسبي زي: storage/qrcodes/product_239.png
 */
private function generateAndSaveProductQrCode(int $productId): string
{
    $url = "https://your-domain.com/product/{$productId}"; // غيّر الدومين حسب موقعك
    $filename = "qrcodes/product_{$productId}.png";

    // احذف القديم لو موجود
    if (Storage::disk('public')->exists($filename)) {
        Storage::disk('public')->delete($filename);
    }

    // توليد الـ QR وحفظه
    $qrImage = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
        ->size(400)
        ->margin(1)
        ->errorCorrection('H')
        ->generate($url);

    Storage::disk('public')->put($filename, $qrImage);

    return 'storage/' . $filename;
}
}