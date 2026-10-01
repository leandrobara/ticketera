<script setup>
const props = defineProps({modelValue:[String,Number,Boolean],label:String,id:String,type:{type:String,default:'text'},options:Array,disabled:Boolean,required:Boolean,min:[String,Number],max:[String,Number],hint:String});
const emit=defineEmits(['update:modelValue']);
function update(event) {emit('update:modelValue',props.type==='checkbox'?event.target.checked:event.target.value);}
</script>
<template>
  <div class="mb-3">
    <label v-if="type !== 'checkbox'" class="form-label" :for="id">{{ label }}<span v-if="required" class="text-danger"> *</span></label>
    <textarea v-if="type==='textarea'" :id="id" :value="modelValue" rows="3" class="form-control" :required="required" :disabled="disabled" @input="update"></textarea>
    <select v-else-if="options" :id="id" :value="modelValue" class="form-select" :required="required" :disabled="disabled" @change="update"><option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option></select>
    <label v-else-if="type==='checkbox'" class="form-check"><input :id="id" type="checkbox" :checked="modelValue" class="form-check-input" :disabled="disabled" @change="update"><span class="form-check-label">{{ label }}</span></label>
    <input v-else :id="id" :type="type" :value="modelValue" class="form-control" :disabled="disabled" :required="required" :min="min" :max="max" :step="type==='number'?'any':undefined" @input="update">
    <small v-if="hint" class="form-hint">{{ hint }}</small>
  </div>
</template>
