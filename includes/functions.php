<?php
// includes/functions.php

/**
 * Call Generative AI (Google Gemini) to get an analysis report.
 * If API Key is not set or empty, generate a mock response.
 */
function get_ai_analysis($scoreData, $type, $studentName, $activityName) {
    // API KEY SETUP (Replace with real key)
    $api_key = ''; 

    $prompt = "วิเคราะห์ระดับ Soft Skill ของนักศึกษาชื่อ $studentName สำหรับกิจกรรม $activityName. ";
    $prompt .= "นี่คือผลการประเมินแบบ " . ($type == 'pre' ? 'ก่อนเข้าร่วม' : 'หลังเข้าร่วม') . " กิจกรรม:\n";
    foreach($scoreData as $skill => $score) {
        $prompt .= "- $skill: ได้คะแนนภาพรวม $score ส่วนคะแนนเต็ม 10\n";
    }
    $prompt .= "\nช่วยพิมพ์คำอธิบายผลลัพธ์ (Analysis) สั้นๆ 2-3 บรรทัด และให้คำแนะนำในการพัฒนาทักษะ (Recommendation) แยกเป็น 2 ส่วนชัดเจน รูปแบบดังนี้:\n";
    $prompt .= "Analysis: [ข้อความอธิบายผลการประเมินต่างๆ โดยยึดตามคะแนนที่ได้]\nRecommendation: [ข้อความคำแนะนำว่าควรพัฒนาด้านใดเพิ่มเติมบ้าง]";

    if (empty($api_key)) {
        // Mock response
        if($type == 'pre') {
            $mockAnalysis = "จากการประเมินก่อนเข้าร่วมกิจกรรมพบว่า $studentName มีทักษะในภาพรวมอยู่ในระดับกลาง โดยเฉพาะด้านที่ได้คะแนนน้อยซึ่งอาจจะต้องพัฒนาเสริมเพิ่มเติม";
            $mockRec = "แนะนำให้ตั้งใจทำกิจกรรม $activityName อย่างเต็มที่ เพื่อเสริมทักษะในด้านที่ยังขาดและเปิดรับประสบการณ์ใหม่ๆ ให้ได้มากที่สุด";
        } else {
            $mockAnalysis = "ภายหลังจากการเข้าร่วมกิจกรรม $activityName พบว่าคะแนนในภาพรวมมีการพัฒนาดีขึ้นอย่างชัดเจน ซึ่งสะท้อนให้เห็นว่า $studentName สามารถปรับตัวและนำสิ่งที่เรียนรู้ไปประยุกต์ใช้ได้";
            $mockRec = "ควรฝึกฝนการทำงานร่วมกับผู้อื่นอย่างต่อเนื่อง และอาจเข้าร่วมกิจกรรมที่เน้นความเป็นผู้นำและการแก้ปัญหาเฉพาะหน้าเพิ่มเติมในอนาคต";
        }
        return ['analysis' => $mockAnalysis, 'recommendation' => $mockRec];
    }

    // Call Gemini API
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
    
    $data = [
        "contents" => [
            ["parts" => [ ["text" => $prompt] ]]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    
    $resData = json_decode($response, true);
    $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // Parse response
    $analysis = "ระบบ AI ขัดข้อง ไม่สามารถวิเคราะห์ได้ในขณะนี้";
    $recommendation = "กรุณาลองใหม่อีกครั้งในภายหลัง หรือติดต่อผู้ดูแลระบบ";
    
    if(preg_match('/Analysis:\s*(.*?)(?:\nRecommendation:\s*(.*))?$/is', $text, $matches)) {
        $analysis = trim($matches[1]);
        if(isset($matches[2])) $recommendation = trim($matches[2]);
    } else {
        $analysis = trim($text);
    }

    return ['analysis' => $analysis, 'recommendation' => $recommendation];
}
?>
