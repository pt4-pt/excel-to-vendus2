<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('vendus_field')->comment('Campo da API Vendus (principal)');
            $table->string('vendus_field_label')->comment('Label amigável do campo Vendus');
            $table->string('excel_column')->nullable()->comment('Coluna do Excel mapeada para este campo');
            $table->string('field_type')->default('string')->comment('Tipo do campo (string, number, boolean)');
            $table->boolean('is_required')->default(false)->comment('Se o campo é obrigatório na Vendus');
            $table->string('default_value')->nullable()->comment('Valor padrão se não encontrado no Excel');
            $table->text('description')->nullable()->comment('Descrição do campo Vendus');
            $table->boolean('is_active')->default(true)->comment('Se o mapeamento está ativo');
            $table->timestamps();
            
            // Índices para melhor performance
            $table->unique('vendus_field');
            $table->index('excel_column');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_mappings');
    }
};
