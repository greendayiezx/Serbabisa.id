/**
 * Ikon pengemudi motor untuk penanda di peta.
 *
 * Dikembalikan sebagai string HTML, bukan komponen Vue, karena Leaflet
 * membangun penandanya dari HTML mentah — komponen Vue tidak pernah dipasang
 * di dalam divIcon.
 *
 * ID GRADIENNYA DIBERI AWALAN, dan itu bukan kerapian belaka: id di dalam
 * <defs> berlaku sedokumen. Dua penanda dengan id yang sama membuat yang kedua
 * memakai definisi milik yang pertama, dan kalau yang pertama dibuang dari DOM
 * `url(#...)` milik yang kedua tidak menunjuk apa pun lagi — gradiennya hilang
 * tanpa satu pun galat, motornya sekadar jadi hitam.
 */
let urutan = 0

export function ikonMotorHtml(ukuran = 56): string {
  const uid = `motor-${++urutan}`
  const biru = `${uid}-biru`
  const biruTua = `${uid}-biruTua`

  /*
   * viewBox dipersempit dari 0 0 512 512 ke kotak yang benar-benar berisi
   * gambar. Dengan viewBox aslinya, hampir sepertiga penanda adalah ruang
   * kosong — motornya tampak kecil dan titik yang ditunjuk meleset dari
   * pusatnya.
   */
  return `<svg viewBox="60 10 400 448" width="${ukuran}" height="${ukuran}" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 5px 10px rgba(0,0,0,0.32))">
  <defs>
    <linearGradient id="${biru}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#1683FF"/>
      <stop offset="1" stop-color="#0756D9"/>
    </linearGradient>
    <linearGradient id="${biruTua}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#084EBD"/>
      <stop offset="1" stop-color="#06348E"/>
    </linearGradient>
  </defs>

  <circle cx="150" cy="383" r="57" fill="#172235"/>
  <circle cx="150" cy="383" r="35" fill="#E7EDF5"/>
  <circle cx="150" cy="383" r="20" fill="#8C98AA"/>
  <circle cx="150" cy="383" r="8" fill="#344054"/>

  <circle cx="394" cy="383" r="57" fill="#172235"/>
  <circle cx="394" cy="383" r="35" fill="#E7EDF5"/>
  <circle cx="394" cy="383" r="20" fill="#8C98AA"/>
  <circle cx="394" cy="383" r="8" fill="#344054"/>

  <path d="M92 327 C105 306 130 293 159 294 L268 307 C288 310 302 325 303 345 L302 365 L205 365 C193 333 171 321 145 321 C123 321 106 331 96 350 Z" fill="url(#${biru})"/>
  <path d="M100 349 C113 326 132 316 157 316 C184 316 204 333 214 358 L343 358 C355 358 365 367 365 380 L365 394 L202 394 C193 362 174 344 149 344 C130 344 114 354 104 370 Z" fill="url(#${biruTua})"/>
  <path d="M99 330 C133 306 193 312 241 328 C260 335 274 349 280 366 L205 366 C193 337 174 321 147 321 C128 321 111 331 99 348 Z" fill="#1683FF"/>
  <path d="M136 292 C151 278 184 277 216 283 L273 294 C282 296 288 304 286 311 C284 318 277 322 268 321 L159 311 C147 310 138 303 136 292 Z" fill="#202B3D"/>
  <path d="M300 307 C309 279 325 253 351 238 C368 228 393 229 408 242 L425 260 C433 270 434 284 428 295 L408 333 C400 347 386 357 370 359 L318 359 C300 355 291 338 300 307 Z" fill="url(#${biru})"/>
  <path d="M354 274 C376 267 399 274 410 291 L427 317 C433 327 431 340 423 348 C414 357 399 361 384 358 L348 348 L329 316 Z" fill="#0756D9"/>
  <path d="M349 349 C361 335 381 327 402 330 C420 333 434 344 442 359 L430 367 C418 351 403 345 389 346 C374 347 362 354 354 365 Z" fill="#1683FF"/>
  <path d="M380 285 L394 288 L409 363 L397 367 Z" fill="#AEB9C8"/>
  <path d="M354 247 L346 190 L356 188 L370 246 Z" fill="#273449"/>

  <ellipse cx="349" cy="180" rx="15" ry="20" fill="#263348"/>
  <path d="M349 198 L355 213" stroke="#263348" stroke-width="6" stroke-linecap="round"/>
  <rect x="350" y="239" width="54" height="12" rx="6" fill="#273449"/>
  <path d="M382 239 C397 239 410 248 413 261 L414 275 C414 281 409 285 403 284 L384 280 C376 278 372 271 374 263 L376 249 C377 243 379 240 382 239 Z" fill="#F7FAFF"/>
  <path d="M92 319 C82 320 75 327 75 337 C75 346 82 353 92 353 L106 353 L111 326 C106 321 100 319 92 319 Z" fill="#FF3B3B"/>

  <path d="M211 146 C234 137 260 143 276 160 L305 195 C314 207 314 224 306 237 L276 280 C265 295 247 300 230 293 L191 275 C177 269 169 255 171 239 L180 181 C183 164 194 152 211 146 Z" fill="url(#${biru})"/>
  <path d="M187 180 C192 157 211 146 229 148 C214 171 207 202 208 235 C208 258 220 276 239 290 L216 284 C192 278 176 260 174 238 Z" fill="#0B5ED7"/>
  <path d="M258 184 C271 184 284 190 296 199 L347 231 C354 235 356 244 352 251 C348 258 339 260 331 256 L272 227 C256 220 248 203 252 191 C253 187 255 185 258 184 Z" fill="#1683FF"/>
  <path d="M328 225 L352 233 C359 236 361 244 357 250 L351 258 L326 247 Z" fill="#084EBB"/>
  <circle cx="356" cy="246" r="13" fill="#F3B27D"/>
  <path d="M239 264 C258 260 278 267 290 282 L319 318 L299 336 L265 307 L225 292 Z" fill="#063E9E"/>
  <path d="M299 314 L324 324 L310 356 L284 348 Z" fill="#073F9E"/>
  <path d="M278 343 C289 339 305 343 316 351 L323 359 C327 364 323 371 316 372 L278 372 C269 372 263 367 266 360 C268 352 271 346 278 343 Z" fill="#202B3D"/>
  <path d="M279 360 L305 364" stroke="#F5F8FC" stroke-width="5" stroke-linecap="round"/>

  <path d="M220 143 L222 126 L250 125 L253 149 Z" fill="#F3B27D"/>
  <path d="M207 91 C213 65 238 51 264 59 C287 66 301 89 296 113 L291 132 C287 148 271 157 254 154 L231 149 C213 145 203 128 205 110 Z" fill="#F3B27D"/>
  <path d="M272 94 C282 94 291 99 296 107 L291 130 C287 145 273 152 260 151 L270 137 C277 127 280 114 272 94 Z" fill="#F7C18F"/>
  <ellipse cx="274" cy="105" rx="4" ry="6" fill="#172235"/>
  <path d="M195 106 C190 76 205 48 233 36 C265 22 300 34 315 61 C321 72 324 84 322 95 L291 94 C282 81 269 75 255 75 C239 75 226 84 220 99 L220 120 C208 118 199 113 195 106 Z" fill="#0878F9"/>
  <path d="M196 105 C203 116 211 120 222 121 L222 108 C219 96 227 83 239 77 C228 77 216 83 207 92 C202 97 198 101 196 105 Z" fill="#0756D9"/>
  <path d="M278 72 C296 72 310 78 319 88 C323 92 320 98 314 99 L281 98 C276 91 275 82 278 72 Z" fill="#063E9E"/>
  <circle cx="218" cy="83" r="7" fill="#DCEAFF"/>
</svg>`
}
