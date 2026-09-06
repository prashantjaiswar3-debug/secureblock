<?php
require_once 'db.php';
check_session_guard();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MNREGA Estimate Generator</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h2 { color: #1a73e8; border-bottom: 2px solid #e8f0fe; padding-bottom: 10px; margin-top: 0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .measure-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .form-group { margin-bottom: 15px; }\n        label { display: block; font-weight: 600; margin-bottom: 8px; color: #5f6368; }
        select, input { width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 8px; box-sizing: border-box; font-size: 14px; }
        select:focus, input:focus { outline: none; border-color: #1a73e8; background-color: #fff; }
        .btn-container { margin-top: 20px; display: flex; gap: 10px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 600; transition: background 0.3s; }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-primary:hover { background: #1557b0; }
        .btn-copy { background: #34a853; color: white; padding: 8px 16px; font-size: 13px; margin-top: 5px; }
        .btn-copy:hover { background: #2d8e47; }
        .output-section { margin-top: 30px; display: none; }
        .result-wrapper { margin-bottom: 25px; }
        .output-box { padding: 20px; border: 1px solid #e8eaed; background: #f8f9fa; border-radius: 8px; white-space: pre-wrap; font-size: 15px; color: #202124; line-height: 1.8; }
    </style>
</head>
<body>

<div class="container">
    <h2>Estimate Generator Application</h2>
    
    <div class="grid">
        <div class="form-group">
            <label for="projectType">Project Type:</label>
            <select id="projectType" onchange="updateMeasurementLabels()">
                <optgroup label="1. Rural Connectivity & Roads">
                    <option value="interlocking">Interlocking / Paver Block Road</option>
                    <option value="khadanja">Khadanja Road (Brick-on-Edge Soling)</option>
                    <option value="road_soil">Chakmarg Road (Earthwork/Pathway)</option>
                    <option value="cc_road">CC Road (Cement Concrete)</option>
                </optgroup>
                <optgroup label="2. Drainage, Water Management & Sanitation">
                    <option value="earthen_drain">Earthen Drain</option>
                    <option value="pukka_drain">Pukka Drain (Concrete/Brick)</option>
                    <option value="canal_cleaning">Canal Cleaning / Excavation</option>
                    <option value="soak_pit">Soak Pit Construction</option>
                </optgroup>
                <optgroup label="3. Land Development & Water Harvesting">
                    <option value="leveling">Farmland Leveling</option>
                    <option value="pond_individual">Farm Pond Excavation</option>
                    <option value="pond_village">Village Pond Excavation / Cleaning (Pokhari)</option>
                </optgroup>
                <optgroup label="4. Livelihood & Animal Husbandry Shelters">
                    <option value="cattle_shed">Cattle Shelter Construction</option>
                    <option value="goat_shelter">Goat Shelter Construction</option>
                </optgroup>
                <optgroup label="5. Organic Farming & Waste Management">
                    <option value="nadep">NADEP Compost Pit</option>
                    <option value="vermi">Vermi Compost Pit</option>
                </optgroup>
                <optgroup label="6. Plantation & Afforestation">
                    <option value="plantation">Block Plantation (Community Land)</option>
                    <option value="individual_plantation">Individual Block Plantation (Beneficiary Land)</option>
                </optgroup>
            </select>
        </div>
        <div class="form-group">
            <label for="blockName">Select Block Name:</label>
            <select id="blockName">
                <option value="Badraon">Badraon</option>
                <option value="Dohri Ghat">Dohri Ghat</option>
                <option value="Fatehpur Madaun">Fatehpur Madaun</option>
                <option value="Ghosi">Ghosi</option>
                <option value="Kopaganj">Kopaganj</option>
                <option value="Mohammadabad Gohana">Mohammadabad Gohana</option>
                <option value="Pardaha">Pardaha</option>
                <option value="Ranipur">Ranipur</option>
                <option value="Ratanpura">Ratanpura</option>
            </select>
        </div>
    </div>

    <div class="grid">
        <div class="form-group">
            <label id="measurementLabel">Measurements Matrix Inputs:</label>
            <div class="measure-grid">
                <input type="text" id="len" placeholder="Length (L)">
                <input type="text" id="wid" placeholder="Width (B)">
                <input type="text" id="dep" placeholder="Depth / Height (D/H)">
            </div>
        </div>
        <div class="form-group">
            <label for="amount">Estimated Amount (Rs.):</label>
            <input type="text" id="amount" placeholder="e.g., 6,95,123.00">
        </div>
    </div>

    <div class="btn-container">
        <button class="btn btn-primary" onclick="generateOutput()">Generate Estimate</button>
    </div>

    <div id="outputSection" class="output-section">
        <div class="result-wrapper">
            <label style="font-weight: bold; color: #1a73e8;">Project Report Body Text:</label>
            <div class="output-box" id="reportText"></div>
            <button class="btn btn-copy" onclick="copyText('reportText')">Copy Report Body</button>
        </div>

        <div class="result-wrapper">
            <label style="font-weight: bold; color: #1a73e8;">Forwarding Note Text:</label>
            <div class="output-box" id="forwardingText"></div>
            <button class="btn btn-copy" onclick="copyText('forwardingText')">Copy Forwarding Note</button>
        </div>
    </div>
</div>

<script>
    // Templates updated: All static category string prefixes completely removed
    const templates = {
        interlocking: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves interlocking road / paver block road work over a distance of ${msr} meters and includes three-stage photos and a signboard. This project will facilitate smoother traffic flow, improve convenience for villagers, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,
        
        khadanja: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Khadanja road construction over a distance of ${msr} meters and includes three-stage photos and a signboard. This project will prevent waterlogging, improve rural pathway connectivity for villagers, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,
        
        road_soil: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Chakmarg road development work with dimensions of ${msr} meters and includes three-stage photos and a signboard. This project will improve access to agricultural fields for farmers, provide a reliable earthen pathway, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        cc_road: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves CC Road construction over a distance of ${msr} meters and includes three-stage photos and a signboard. This project will provide a durable, all-weather concrete pathway for the village and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        earthen_drain: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Earthen Drain excavation and formatting work with dimensions of ${msr} meters and includes three-stage photos and a signboard. This project will ensure proper wastewater disposal, prevent localized flooding, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        pukka_drain: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Pukka Drain construction work with dimensions of ${msr} meters and includes three-stage photos and a signboard. This project will facilitate better sanitation, secure proper drainage flow for the village, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        canal_cleaning: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Canal cleaning and excavation work with dimensions of ${msr} meters and includes three-stage photos and a signboard. This project will restore water flow capacity, enhance irrigation facilities for local farmers, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        soak_pit: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves the construction of ${msr} of Soak Pit and includes three-stage photos and a signboard. This project will prevent greywater accumulation, maintain village sanitation, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        leveling: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Farmland leveling and land development work over an area of ${msr} for target beneficiaries and includes three-stage photos and a signboard. This project will maximize agricultural productivity, improve soil moisture retention, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        pond_individual: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Farm Pond excavation work with dimensions of ${msr} meters for rainwater harvesting and includes three-stage photos and a signboard. This project will support local irrigation, recharge groundwater levels, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        pond_village: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Village Pond (Pokhari) excavation and cleaning work with dimensions of ${msr} meters and includes three-stage photos and a signboard. This project will revive the local water body, fulfill community water requirements, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        cattle_shed: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Cattle Shelter construction work designed as ${msr} for individual beneficiary livestock and includes three-stage photos and a signboard. This project will improve livestock management, support dairy livelihoods, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        goat_shelter: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Goat Shelter construction work designed as ${msr} for individual beneficiary livestock and includes three-stage photos and a signboard. This project will provide safe animal housing, enhance rural livelihood incomes, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        nadep: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves the construction of ${msr} of NADEP Compost tank and includes three-stage photos and a signboard. This project will encourage organic farming practices, manage village waste efficiently, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        vermi: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves the construction of ${msr} of Vermi Compost bed and includes three-stage photos and a signboard. This project will boost the production of organic bio-fertilizers, benefit beneficiary farmers, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        plantation: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Block Plantation work for ${msr} of Plants over designated community land and includes three-stage photos and a signboard. This project will enhance green cover, support environmental conservation, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`,

        individual_plantation: (blk, msr, amt) => `This estimate, prepared under the Mahatma Gandhi National Rural Employment Guarantee Act (MNREGA) scheme, is based on a directive from the Block Development Officer - ${blk}. Following a physical inspection, the project involves Individual Block Plantation work for ${msr} of Plants on private beneficiary land and includes three-stage photos and a signboard. This project will generate long-term asset income for beneficiary families, support agro-forestry, and provide temporary employment opportunities for Job Card Holders. The estimated cost of Rs. ${amt} has been submitted for technical, financial, and administrative approval.`
    };

    function updateMeasurementLabels() {
        const type = document.getElementById('projectType').value;
        const lenInput = document.getElementById('len');
        const widInput = document.getElementById('wid');
        const depInput = document.getElementById('dep');
        const mainLabel = document.getElementById('measurementLabel');

        // Reset inputs and visibility
        lenInput.style.display = "block";
        widInput.style.display = "block";
        depInput.style.display = "block";
        lenInput.disabled = false;
        widInput.disabled = false;
        depInput.disabled = false;

        if (type === "interlocking" || type === "khadanja" || type === "cc_road") {
            mainLabel.innerText = "Sample Measurement: Length × Width (e.g., 300.00x2.00 meters):";
            lenInput.placeholder = "Length (M)";
            widInput.placeholder = "Width (M)";
            depInput.style.display = "none";
        } else if (type === "road_soil") {
            mainLabel.innerText = "Sample Measurement: Length × Width × Height (e.g., 500.00x4.00X1.00 meters):";
            lenInput.placeholder = "Length (M)";
            widInput.placeholder = "Width (M)";
            depInput.placeholder = "Height (M)";
        } else if (type === "earthen_drain" || type === "pukka_drain" || type === "canal_cleaning" || type === "pond_individual") {
            mainLabel.innerText = "Sample Measurement: Length × Width × Depth (e.g., 500.00x4.00X1.00 meters):";
            lenInput.placeholder = "Length (M)";
            widInput.placeholder = "Width (M)";
            depInput.placeholder = "Depth (M)";
        } else if (type === "pond_village") {
            mainLabel.innerText = "Sample Measurement: Dimensions (e.g., 50.00x40.00x2.50 meters):";
            lenInput.placeholder = "Length (M)";
            widInput.placeholder = "Width (M)";
            depInput.placeholder = "Depth (M)";
        } else if (type === "soak_pit" || type === "nadep" || type === "vermi") {
            mainLabel.innerText = "Sample Measurement: 1 unit (No dimensions needed):";
            lenInput.placeholder = "Units (e.g. 1)";
            widInput.style.display = "none";
            depInput.style.display = "none";
        } else if (type === "leveling") {
            mainLabel.innerText = "Sample Measurement: Hectares & Bighas (e.g., 2.50 hectares / 10.00 bighas):";
            lenInput.placeholder = "Hectares";
            widInput.placeholder = "Bighas";
            depInput.style.display = "none";
        } else if (type === "cattle_shed") {
            mainLabel.innerText = "Sample Measurement: 6 units of livestock (or 1 unit for single beneficiary):";
            lenInput.placeholder = "Units (e.g. 1)";
            widInput.style.display = "none";
            depInput.style.display = "none";
        } else if (type === "goat_shelter") {
            mainLabel.innerText = "Sample Measurement: 10 units of goats (or 1 unit for single beneficiary):";
            lenInput.placeholder = "Units (e.g. 1)";
            widInput.style.display = "none";
            depInput.style.display = "none";
        } else if (type === "plantation" || type === "individual_plantation") {
            mainLabel.innerText = "Sample Measurement: Units of Plants (e.g., 1000 or 200 plants):";
            lenInput.placeholder = "Plants count";
            widInput.style.display = "none";
            depInput.style.display = "none";
        }
    }

    /**
     * Formats currency parameters cleanly into the Indian Numbering System (##,##,###.00)
     */
    function formatIndianCurrency(amountStr) {
        if (!amountStr || amountStr.trim() === "") return "...";
        
        // Clean non-numeric crumbs down to base numeric floating formats
        let num = parseFloat(amountStr.replace(/,/g, ''));
        if (isNaN(num)) return "...";
        
        let parts = num.toFixed(2).split(".");
        let lastThree = parts[0].substring(parts[0].length - 3);
        let otherBits = parts[0].substring(0, parts[0].length - 3);
        
        if (otherBits !== '') {
            lastThree = ',' + lastThree;
        }
        
        let formattedInteger = otherBits.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree;
        return formattedInteger + "." + parts[1];
    }

    // Attach listener to handle live auto-formatting inside the input box when focus is lost
    document.getElementById('amount').addEventListener('blur', function() {
        if (this.value.trim() !== "") {
            let formatted = formatIndianCurrency(this.value);
            if (formatted !== "...") {
                this.value = formatted;
            }
        }
    });

    function formatDecimal(val) {
        if (!val || isNaN(val)) return "...";
        return parseFloat(val).toFixed(2);
    }

    function generateOutput() {
        const type = document.getElementById('projectType').value;
        const block = document.getElementById('blockName').value;
        
        let rawAmt = document.getElementById('amount').value;
        // Format to Indian currency style logic
        const amt = formatIndianCurrency(rawAmt);
        
        // Push the formatted layout value back to display inside the input element
        if (rawAmt.trim() !== "" && amt !== "...") {
            document.getElementById('amount').value = amt;
        }
        
        const l = formatDecimal(document.getElementById('len').value);
        const b = formatDecimal(document.getElementById('wid').value);
        const d = formatDecimal(document.getElementById('dep').value);
        
        let msr = "";
        
        if (type === "soak_pit" || type === "nadep" || type === "vermi") {
            msr = (l !== "..." ? parseInt(l) : "1") + " unit";
        } else if (type === "cattle_shed") {
            msr = (l !== "..." ? parseInt(l) : "1") + " unit";
        } else if (type === "goat_shelter") {
            msr = (l !== "..." ? parseInt(l) : "1") + " unit";
        } else if (type === "plantation" || type === "individual_plantation") {
            msr = (l !== "..." ? parseInt(l) : (type === "plantation" ? "1000" : "200")) + " units";
        } else if (type === "leveling") {
            if (l !== "..." && b !== "...") {
                msr = `${l} hectares (${b} bighas)`;
            } else {
                msr = l !== "..." ? `${l} hectares` : "2.50 hectares (10.00 bighas)";
            }
        } else {
            if (l !== "..." && b !== "..." && d !== "...") msr = `${l}x${b}x${d}`;
            else if (l !== "..." && b !== "...") msr = `${l}x${b}`;
            else msr = (l !== "..." ? l : (b !== "..." ? b : (d !== "..." ? d : "...")));
        }

        if (templates[type]) {
            document.getElementById('reportText').innerText = templates[type](block, msr, amt);
            document.getElementById('forwardingText').innerText = "Please see the attached for technical and administrative approval.";
            document.getElementById('outputSection').style.display = 'block';
        }
    }

    function copyText(id) {
        const text = document.getElementById(id).innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert("Text copied successfully!");
        });
    }

    // Call onload to initialize layout configuration
    window.onload = updateMeasurementLabels;
</script>

</body>
</html>