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
        Schema::create('document_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('vendus_field')->comment('Campo de metadados para documentos Vendus');
            $table->string('vendus_field_label')->comment('Label amigável do campo');
            $table->string('metadata_key')->nullable()->comment('Chave de metadado mapeada (ex.: filename, series, customer_name)');
            $table->string('field_type')->default('string')->comment('Tipo do campo (string, number, boolean)');
            $table->boolean('is_required')->default(false)->comment('Se o campo é obrigatório');
            $table->string('default_value')->nullable()->comment('Valor padrão se não fornecido');
            $table->text('description')->nullable()->comment('Descrição do campo');
            $table->boolean('is_active')->default(true)->comment('Se o mapeamento está ativo');
            $table->timestamps();

            $table->unique('vendus_field');
            $table->index('metadata_key');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_field_mappings');
    }
};