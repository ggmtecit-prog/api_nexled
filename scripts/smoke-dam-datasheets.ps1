$apiKey = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b"
$apiUrl = "http://localhost/api_nexled/api/?endpoint=datasheet"
$outputDir = Join-Path (Resolve-Path ".").Path "output/pdf"

New-Item -ItemType Directory -Force $outputDir | Out-Null

$headers = @{
    "X-API-Key" = $apiKey
}

$defaults = @{
    idioma = "pt"
    empresa = "0"
    vedante = "5"
    acrescimo = "0"
    ip = "0"
    fixacao = "0"
    fonte = "0"
    caboligacao = "0"
    conectorligacao = "0"
    tamanhocaboligacao = "0"
    finalidade = "0"
}

$samples = @(
    @{
        family = "11"
        filename = "family-11.pdf"
        body = @{
            referencia = "11007502111010100"
            descricao = "Barra LED 24V"
            lente = "Clear"
            acabamento = "Alu"
            opcao = "00"
            conectorcabo = "0"
            tipocabo = "branco"
            tampa = "0"
        }
    }
    @{
        family = "29"
        filename = "family-29.pdf"
        body = @{
            referencia = "29012022191010000"
            descricao = "LLED Downlight 120mm WW303 PL6.5W"
            lente = "Clear"
            acabamento = "BR"
            opcao = "00"
            conectorcabo = "0"
            tipocabo = "branco"
            tampa = "0"
        }
    }
    @{
        family = "30"
        filename = "family-30.pdf"
        body = @{
            referencia = "30111131181010000"
            descricao = "LLED 4L 11x11cm WW273 HE PL5W"
            lente = "Clear"
            acabamento = "BR"
            opcao = "00"
            conectorcabo = "0"
            tipocabo = "branco"
            tampa = "0"
        }
    }
    @{
        family = "32"
        filename = "family-32.pdf"
        body = @{
            referencia = "32096036111011000"
            descricao = "LLED BT 24V 960 WW573 HE"
            lente = "Clear"
            acabamento = "PC"
            opcao = "00"
            conectorcabo = "dc24"
            tipocabo = "branco"
            tampa = "0"
        }
    }
    @{
        family = "48"
        filename = "family-48.pdf"
        body = @{
            referencia = "48007002110010000"
            descricao = "LLED Dynamic 70mm 3000K PL 27W"
            lente = "30&deg;"
            acabamento = "Az+Inox"
            opcao = "00"
            conectorcabo = "0"
            tipocabo = "branco"
            tampa = "0"
        }
    }
    @{
        family = "55"
        filename = "family-55.pdf"
        body = @{
            referencia = "55007524111010100"
            descricao = "LLED Barra 12V 10 NW403"
            lente = "Clear"
            acabamento = "Alu"
            opcao = "00"
            conectorcabo = "0"
            tipocabo = "branco"
            tampa = "0"
        }
    }
    @{
        family = "58"
        filename = "family-58.pdf"
        body = @{
            referencia = "58007532141010000"
            descricao = "LLED B 24V HOT 10 WW303 HE DL"
            lente = "Clear"
            acabamento = "Alu"
            opcao = "00"
            conectorcabo = "0"
            tipocabo = "branco"
            tampa = "2"
        }
    }
)

$results = foreach ($sample in $samples) {
    $payload = @{}

    foreach ($entry in $defaults.GetEnumerator()) {
        $payload[$entry.Key] = $entry.Value
    }

    foreach ($entry in $sample.body.GetEnumerator()) {
        $payload[$entry.Key] = $entry.Value
    }

    $jsonBody = $payload | ConvertTo-Json -Compress
    $targetPath = Join-Path $outputDir $sample.filename

    try {
        Invoke-WebRequest -Uri $apiUrl -Method POST -Headers $headers -ContentType "application/json" -Body $jsonBody -OutFile $targetPath

        [pscustomobject]@{
            family = $sample.family
            ok = $true
            output = $targetPath
            bytes = (Get-Item $targetPath).Length
            error = ""
        }
    } catch {
        $errorMessage = $_.Exception.Message

        if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
            $errorMessage = $_.ErrorDetails.Message
        }

        [pscustomobject]@{
            family = $sample.family
            ok = $false
            output = $targetPath
            bytes = 0
            error = $errorMessage
        }
    }
}

$results | ConvertTo-Json -Depth 4
