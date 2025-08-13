<template>
  <CCard class="mb-4">
    <CCardHeader>
      <strong>Analisis AI untuk Indikator Kemiskinan</strong>
    </CCardHeader>
    <CCardBody>
      <p>
        Klik tombol di bawah untuk mendapatkan analisis tren data kemiskinan (5 tahun terakhir)
        secara otomatis menggunakan AI.
      </p>

      <CButton color="primary" @click="fetchInsight" :disabled="isLoading">
        <CSpinner component="span" size="sm" aria-hidden="true" v-if="isLoading" />
        {{ isLoading ? ' Menganalisis...' : 'Dapatkan Insight AI' }}
      </CButton>

      <CCard class="mt-4" v-if="insight && !isLoading">
        <CCardHeader> <CIcon name="cil-lightbulb" /> Insight dari Gemini AI </CCardHeader>
        <CCardBody>
          <pre style="white-space: pre-wrap; font-family: inherit; font-size: inherit">{{
            insight
          }}</pre>
        </CCardBody>
      </CCard>

      <CAlert color="danger" class="mt-4" v-if="error && !isLoading">
        <strong>Terjadi Kesalahan:</strong> {{ error }}
      </CAlert>
    </CCardBody>
  </CCard>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
// Import komponen CoreUI yang digunakan
// import { CCard, CCardHeader, CCardBody, CButton, CSpinner, CAlert, CIcon } from '@coreui/vue'
// BENAR
import { CCard, CCardHeader, CCardBody } from '@coreui/vue'
import { CButton } from '@coreui/vue'
import { CSpinner } from '@coreui/vue'
import { CAlert } from '@coreui/vue'
import { CIcon } from '@coreui/icons-vue' // <-- Perhatikan, CIcon diimpor dari '@coreui/icons-vue'

// --- State Management ---
// ref() digunakan untuk membuat variabel reaktif
const isLoading = ref(false) // Untuk mengontrol status loading
const insight = ref('') // Untuk menyimpan hasil insight dari API
const error = ref('') // Untuk menyimpan pesan error

// --- Method untuk memanggil API ---
const fetchInsight = async () => {
  // Reset state setiap kali tombol diklik
  isLoading.value = true
  insight.value = ''
  error.value = ''

  try {
    // Ganti 81 dengan ID indikator kemiskinan yang sesuai di database Anda
    // Pastikan URL-nya benar (sesuai dengan `php artisan serve`)
    const response = await axios.post('http://127.0.0.1:8000/api/insight/81')

    // Simpan hasil insight ke variabel
    insight.value = response.data.insight
  } catch (e) {
    // Tangani jika ada error dari API atau koneksi
    error.value = 'Gagal terhubung ke server atau API mengembalikan error. Silakan coba lagi.'
    console.error(e) // Tampilkan error detail di console untuk debugging
  } finally {
    // Hentikan status loading, baik berhasil maupun gagal
    isLoading.value = false
  }
}
</script>

<style scoped>
/* Menjaga agar format teks dari AI terlihat seperti paragraf biasa */
pre {
  white-space: pre-wrap;
  font-family: inherit;
  font-size: inherit;
  margin: 0;
}
</style>
